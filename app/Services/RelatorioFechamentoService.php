<?php

namespace App\Services;

use App\Models\Caixa;
use App\Models\CaixaLocal;
use App\Models\User;
use App\Models\Venda;
use App\Support\AutorizacaoSupervisor;
use App\Support\OcorrenciaCaixa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dados do relatório de fechamento de um caixa.
 * dados(): só com o SQLite local (no caixa, funciona com o servidor fora do ar).
 * dadosServidor(): com o MySQL (painel administrativo).
 */
class RelatorioFechamentoService
{
    /** @var callable id do usuário => código */
    private $codigoDe;

    public const FORMAS = [
        'dinheiro' => 'Dinheiro',
        'pix' => 'Pix',
        'credito' => 'Cartão crédito',
        'debito' => 'Cartão débito',
    ];

    public function dados(CaixaLocal $caixa): array
    {
        $db = DB::connection('sqlite_local');

        $vendas = $db->table('vendas_pendentes')
            ->where(function ($q) use ($caixa) {
                $q->where('caixa_uuid', $caixa->uuid);

                if ($caixa->id_central) {
                    $q->orWhere('caixa_id_central', $caixa->id_central);
                }
            })
            ->orderBy('vendida_em')
            ->orderBy('id')
            ->get();

        $ocorrencias = $db->table('ocorrencias_caixa')
            ->where('caixa_uuid', $caixa->uuid)
            ->orderBy('id')
            ->get();

        return $this->montar(
            (float) $caixa->valor_abertura,
            $caixa->valor_fechamento_informado,
            $vendas,
            $ocorrencias,
            fn ($id) => $db->table('usuarios_cache')->where('id', $id)->value('codigo')
        );
    }

    /**
     * Mesmo relatório montado com os dados do servidor (painel administrativo).
     * Vendas, pagamentos e itens vêm do MySQL e as ocorrências da tabela caixa_ocorrencias (sincronizada pelos caixas).
     */
    public function dadosServidor(Caixa $caixa): array
    {
        $vendas = Venda::with(['pagamentos', 'itens'])
            ->where('caixa_id', $caixa->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            // Mesmo formato das linhas de vendas_pendentes do caixa
            ->map(fn (Venda $v) => (object) [
                'uuid' => $v->uuid,
                'numero_nfce' => $v->numero_nfce,
                'vendida_em' => $v->created_at,
                'updated_at' => $v->updated_at,
                'total' => (float) $v->total,
                'troco' => (float) $v->troco,
                'desconto' => (float) $v->desconto,
                'forma_pagamento' => $v->forma_pagamento,
                'status' => $v->status,
                'status_fiscal' => $v->status,
                'motivo_cancelamento' => $v->motivo_cancelamento,
                'pagamentos' => json_encode($v->pagamentos->map(fn ($p) => [
                    'forma_pagamento' => $p->forma_pagamento,
                    'valor' => (float) $p->valor,
                ])->all()),
                'itens' => json_encode($v->itens->map(fn ($i) => [
                    'preco_unitario' => (float) $i->preco_unitario,
                    'quantidade' => (float) $i->quantidade,
                ])->all()),
            ]);

        $ocorrencias = DB::table('caixa_ocorrencias')
            ->where('caixa_uuid', $caixa->uuid)
            ->orderBy('ocorrida_em')
            ->orderBy('id')
            ->get()
            ->map(function ($o) {
                $o->created_at = $o->ocorrida_em ?? $o->created_at;

                return $o;
            });

        return $this->montar(
            (float) $caixa->valor_abertura,
            $caixa->valor_fechamento_informado,
            $vendas,
            $ocorrencias,
            fn ($id) => User::whereKey($id)->value('codigo')
        );
    }

    /**
     * @param Collection $vendas      linhas no formato de vendas_pendentes
     * @param Collection $ocorrencias linhas no formato de ocorrencias_caixa
     * @param callable   $codigoDe    id do usuário => código (para "Liberado por")
     */
    private function montar(float $valorAbertura, $informado, Collection $vendas, Collection $ocorrencias, callable $codigoDe): array
    {
        $this->codigoDe = $codigoDe;

        $porUuid = $vendas->keyBy('uuid');
        $numeroDe = fn (?string $uuid) => $uuid && isset($porUuid[$uuid]) && $porUuid[$uuid]->numero_nfce
            ? str_pad($porUuid[$uuid]->numero_nfce, 6, '0', STR_PAD_LEFT)
            : 's/ NFC-e';

        // ---------------- Vendas ----------------
        $linhas = [];
        $formas = array_fill_keys(array_keys(self::FORMAS), 0.0);
        $totalBruto = 0.0;
        $totalDescontos = 0.0;
        $totalLiquido = 0.0;
        $troco = 0.0;
        $qtdValidas = 0;
        $qtdCanceladas = 0;
        $totalCancelado = 0.0;

        foreach ($vendas as $v) {
            $cancelada = $this->cancelada($v);
            $pagamentos = json_decode($v->pagamentos ?? '[]', true) ?: [];
            $itens = json_decode($v->itens ?? '[]', true) ?: [];

            $linhas[] = [
                'numero' => $v->numero_nfce ? str_pad($v->numero_nfce, 6, '0', STR_PAD_LEFT) : 's/ NFC-e',
                'hora' => Carbon::parse($v->vendida_em)->format('H:i'),
                'pagamento' => $this->rotuloPagamentos($pagamentos, $v->forma_pagamento),
                'total' => (float) $v->total,
                'situacao' => match (true) {
                    $cancelada => 'CANC',
                    $v->status_fiscal === 'contingencia' => 'CONT',
                    $v->status_fiscal === 'emitida' => '',
                    default => 'PEND',
                },
            ];

            if ($cancelada) {
                $qtdCanceladas++;
                $totalCancelado += (float) $v->total;
                continue;
            }

            $qtdValidas++;
            $totalLiquido += (float) $v->total;
            $totalDescontos += (float) $v->desconto;
            $troco += (float) $v->troco;
            $totalBruto += collect($itens)->sum(fn ($i) => (float) $i['preco_unitario'] * (float) $i['quantidade']);

            if (!$pagamentos && $v->forma_pagamento) {
                $pagamentos = [['forma_pagamento' => $v->forma_pagamento, 'valor' => $v->total]];
            }

            foreach ($pagamentos as $p) {
                $chave = array_key_exists($p['forma_pagamento'] ?? '', self::FORMAS) ? $p['forma_pagamento'] : 'dinheiro';
                $formas[$chave] += (float) $p['valor'];
            }
        }

        // ---------------- Cancelamentos de NFC-e ----------------
        $ocorrenciasCancelamento = $ocorrencias->where('tipo', OcorrenciaCaixa::CANCELAMENTO_NFCE)->keyBy('venda_uuid');

        $cancelamentos = $vendas->filter(fn ($v) => $this->cancelada($v))->map(function ($v) use ($ocorrenciasCancelamento) {
            $o = $ocorrenciasCancelamento[$v->uuid] ?? null;

            return [
                'numero' => $v->numero_nfce ? str_pad($v->numero_nfce, 6, '0', STR_PAD_LEFT) : 's/ NFC-e',
                'hora' => Carbon::parse($o->created_at ?? $v->updated_at)->format('H:i'),
                'valor' => (float) $v->total,
                'motivo' => $o->descricao ?? $v->motivo_cancelamento,
                'liberado_por' => $this->liberadoPor($o),
            ];
        })->values()->all();

        // ---------------- Descontos ----------------
        $descontos = $ocorrencias
            ->whereIn('tipo', [OcorrenciaCaixa::DESCONTO_ITEM, OcorrenciaCaixa::DESCONTO_GLOBAL])
            ->filter(fn ($o) => !isset($porUuid[$o->venda_uuid]) || !$this->cancelada($porUuid[$o->venda_uuid]))
            ->map(fn ($o) => [
                'numero' => $numeroDe($o->venda_uuid),
                'hora' => Carbon::parse($o->created_at)->format('H:i'),
                'descricao' => $o->tipo === OcorrenciaCaixa::DESCONTO_GLOBAL ? 'No total da venda' : $o->descricao,
                'valor' => (float) $o->valor,
                'liberado_por' => $this->liberadoPor($o),
            ])->values()->all();

        // Vendas com desconto de antes deste registro existir (sem ocorrência gravada)
        $comOcorrencia = $ocorrencias
            ->whereIn('tipo', [OcorrenciaCaixa::DESCONTO_ITEM, OcorrenciaCaixa::DESCONTO_GLOBAL])
            ->pluck('venda_uuid')->filter()->unique()->all();
        foreach ($vendas as $v) {
            if ((float) $v->desconto > 0 && !$this->cancelada($v) && !in_array($v->uuid, $comOcorrencia, true)) {
                $descontos[] = [
                    'numero' => $numeroDe($v->uuid),
                    'hora' => Carbon::parse($v->vendida_em)->format('H:i'),
                    'descricao' => 'Desconto na venda',
                    'valor' => (float) $v->desconto,
                    'liberado_por' => 'não registrado',
                ];
            }
        }

        // ---------------- Outras liberações do supervisor ----------------
        // Descontos e cancelamento de NFC-e já aparecem acima com o supervisor; aqui fica o resto
        // (cancelar item, cancelar cupom, inutilização...)
        $tiposJaListados = ['desconto_item', 'desconto_global', 'cancelar_nfce'];

        $liberacoes = $ocorrencias->where('tipo', OcorrenciaCaixa::AUTORIZACAO)
            ->reject(fn ($o) => in_array(AutorizacaoSupervisor::tipoDaAcao($o->acao) ?? $o->acao, $tiposJaListados, true))
            ->map(fn ($o) => [
                'hora' => Carbon::parse($o->created_at)->format('H:i'),
                'acao' => $this->rotuloAcao($o->acao),
                'liberado_por' => $this->liberadoPor($o),
            ])->values()->all();

        // ---------------- Gaveta ----------------
        $dinheiroEsperado = $valorAbertura + $formas['dinheiro'] - $troco;
        $informado = $informado !== null ? (float) $informado : null;

        return [
            'vendas' => $linhas,
            'qtd_validas' => $qtdValidas,
            'qtd_canceladas' => $qtdCanceladas,
            'total_cancelado' => round($totalCancelado, 2),
            'total_bruto' => round($totalBruto, 2),
            'total_descontos' => round($totalDescontos, 2),
            'total_liquido' => round($totalLiquido, 2),
            'formas' => collect($formas)->filter(fn ($v) => $v > 0)->map(fn ($v) => round($v, 2))->all(),
            'troco' => round($troco, 2),
            'cancelamentos' => $cancelamentos,
            'descontos' => $descontos,
            'liberacoes' => $liberacoes,
            'dinheiro_esperado' => round($dinheiroEsperado, 2),
            'informado' => $informado,
            'diferenca' => $informado !== null ? round($informado - $dinheiroEsperado, 2) : null,
        ];
    }

    private function cancelada(object $v): bool
    {
        return $v->status_fiscal === 'cancelada' || $v->status === 'cancelada';
    }

    private function rotuloPagamentos(array $pagamentos, ?string $formaAntiga): string
    {
        $chaves = collect($pagamentos)->pluck('forma_pagamento')->filter()->unique()->values();

        if ($chaves->isEmpty() && $formaAntiga) {
            $chaves = collect([$formaAntiga]);
        }

        if ($chaves->count() > 1) {
            return 'Múltiplo';
        }

        return self::FORMAS[$chaves->first()] ?? ($chaves->first() ?: '-');
    }

    private function liberadoPor(?object $o): string
    {
        if (!$o) {
            return 'não registrado';
        }

        if ($o->liberado_operador) {
            return 'operador (liberado)';
        }

        if ($o->supervisor_id) {
            $codigo = ($this->codigoDe)($o->supervisor_id);

            return trim(($codigo ? str_pad($codigo, 4, '0', STR_PAD_LEFT) . ' - ' : '') . ($o->supervisor_nome ?? 'Supervisor #' . $o->supervisor_id));
        }

        return 'não registrado';
    }

    private function rotuloAcao(?string $acao): string
    {
        $config = config('permissoes.caixa.acoes.' . $acao);

        if (is_string($config)) {
            return $config;
        }

        if (is_array($config)) {
            return $config['nome'] ?? $config['label'] ?? $config['descricao'] ?? (string) $acao;
        }

        return ucfirst(str_replace('_', ' ', (string) $acao));
    }
}