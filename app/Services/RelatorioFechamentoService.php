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

    /** De onde vêm as formas de pagamento: 'caixa' (SQLite) ou 'servidor' (MySQL). */
    private string $fonte = 'servidor';

    public const FORMAS = [
        'dinheiro' => 'Dinheiro',
        'pix' => 'Pix',
        'credito' => 'Cartão crédito',
        'debito' => 'Cartão débito',
        'credito_loja' => 'Crédito em loja', // troca/haver: não entra no dinheiro esperado da gaveta
        'convenio' => 'Convênio', // fiado: vira conta a receber, não entra na gaveta
    ];

    public function dados(CaixaLocal $caixa): array
    {
        $db = DB::connection('sqlite_local');
        $this->fonte = 'caixa';

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

        // Baixas de convênio recebidas neste caixa
        $recebimentos = $db->getSchemaBuilder()->hasTable('convenio_recebimentos')
            ? $db->table('convenio_recebimentos')->where('caixa_uuid', $caixa->uuid)->orderBy('recebido_em')->orderBy('id')->get()
                ->map(fn ($r) => (object) [
                    'recebido_em' => $r->recebido_em, 'cliente' => $r->cliente_nome, 'numero_nfce' => $r->numero_nfce,
                    'forma_pagamento' => $r->forma_pagamento, 'valor' => (float) $r->valor,
                ])
            : collect();

        return $this->montar(
            (float) $caixa->valor_abertura,
            $caixa->valor_fechamento_informado,
            $vendas,
            $ocorrencias,
            fn ($id) => $db->table('usuarios_cache')->where('id', $id)->value('codigo'),
            $recebimentos
        );
    }

    /**
     * Mesmo relatório montado com os dados do servidor (painel administrativo).
     * Vendas, pagamentos e itens vêm do MySQL e as ocorrências da tabela caixa_ocorrencias (sincronizada pelos caixas).
     */
    public function dadosServidor(Caixa $caixa): array
    {
        $this->fonte = 'servidor';
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
                'restaurante_origem' => $v->comanda_id ? 'mesa' : ($v->comanda_pedido_id ? 'ifood' : null),
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

        $recebimentos = \App\Models\ContaReceberParcela::with(['conta.cliente:id,nome', 'conta.venda:id,numero_nfce'])
            ->where('recebido_caixa_uuid', $caixa->uuid)
            ->orderBy('updated_at')->orderBy('id')
            ->get()
            ->map(fn ($p) => (object) [
                'recebido_em' => $p->updated_at, 'cliente' => $p->conta?->cliente?->nome, 'numero_nfce' => $p->conta?->venda?->numero_nfce,
                'forma_pagamento' => $p->recebido_forma_caixa, 'valor' => (float) $p->valor_recebido,
            ]);

        return $this->montar(
            (float) $caixa->valor_abertura,
            $caixa->valor_fechamento_informado,
            $vendas,
            $ocorrencias,
            fn ($id) => User::whereKey($id)->value('codigo'),
            $recebimentos
        );
    }

    /**
     * @param Collection $vendas      linhas no formato de vendas_pendentes
     * @param Collection $ocorrencias linhas no formato de ocorrencias_caixa
     * @param callable   $codigoDe    id do usuário => código (para "Liberado por")
     */
    private function montar(float $valorAbertura, $informado, Collection $vendas, Collection $ocorrencias, callable $codigoDe, ?Collection $recebimentos = null): array
    {
        $recebimentos ??= collect();
        $this->codigoDe = $codigoDe;

        $porUuid = $vendas->keyBy('uuid');
        $numeroDe = fn (?string $uuid) => $uuid && isset($porUuid[$uuid]) && $porUuid[$uuid]->numero_nfce
            ? str_pad($porUuid[$uuid]->numero_nfce, 6, '0', STR_PAD_LEFT)
            : 's/ NFC-e';

        // ---------------- Vendas ----------------
        $linhas = [];
        // Formas do cadastro (Cadastros → Caixa → Formas de pagamento); forma sem cadastro aparece pelo próprio código
        $rotulosFormas = \App\Support\FormasCaixa::rotulos($this->fonte) + self::FORMAS;
        $formas = array_fill_keys(array_keys($rotulosFormas), 0.0);
        $totalBruto = 0.0;
        $totalDescontos = 0.0;
        $totalLiquido = 0.0;
        $troco = 0.0;
        $qtdValidas = 0;
        $qtdCanceladas = 0;
        $totalCancelado = 0.0;
        // Vendas do restaurante (mesa/iFood) emitidas neste caixa: o dinheiro não passou por esta gaveta
        $qtdRestaurante = 0;
        $totalRestaurante = 0.0;

        foreach ($vendas as $v) {
            $cancelada = $this->cancelada($v);
            $pagamentos = json_decode($v->pagamentos ?? '[]', true) ?: [];
            $itens = json_decode($v->itens ?? '[]', true) ?: [];

            $linhas[] = [
                'numero' => $v->numero_nfce ? str_pad($v->numero_nfce, 6, '0', STR_PAD_LEFT) : 's/ NFC-e',
                'hora' => Carbon::parse($v->vendida_em)->format('H:i'),
                'pagamento' => (!empty($v->restaurante_origem) ? ($v->restaurante_origem === 'ifood' ? 'iFood · ' : 'Mesa · ') : '')
                    . $this->rotuloPagamentos($pagamentos, $v->forma_pagamento),
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

            if (!empty($v->restaurante_origem)) {
                $qtdRestaurante++;
                $totalRestaurante += (float) $v->total;
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
                $chave = ($p['forma_pagamento'] ?? null) ?: 'dinheiro';
                $formas[$chave] = ($formas[$chave] ?? 0.0) + (float) $p['valor'];
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

        // ---------------- Recebimentos de convênio (baixa no caixa) ----------------
        $convenioRecebido = $recebimentos->groupBy('forma_pagamento')->map(fn ($g) => round((float) $g->sum('valor'), 2))->all();
        $linhasRecebimentos = $recebimentos->map(fn ($r) => [
            'hora' => $r->recebido_em ? Carbon::parse($r->recebido_em)->format('H:i') : '',
            'cliente' => $r->cliente,
            'numero' => $r->numero_nfce ? str_pad($r->numero_nfce, 6, '0', STR_PAD_LEFT) : 's/ NFC-e',
            'forma' => \App\Support\FormasCaixa::rotulo($r->forma_pagamento, $this->fonte),
            'valor' => round((float) $r->valor, 2),
        ])->values()->all();

        // ---------------- Gaveta ----------------
        // Dinheiro de venda + dinheiro recebido de convênio − troco
        // Na gaveta fica o que foi pago em formas de meio 01 (dinheiro), de venda e de convênio recebido
        $codigosDinheiro = \App\Support\FormasCaixa::codigosDinheiro($this->fonte);
        $dinheiroVendas = array_sum(array_intersect_key($formas, array_flip($codigosDinheiro)));
        $dinheiroConvenio = array_sum(array_intersect_key($convenioRecebido, array_flip($codigosDinheiro)));
        $dinheiroEsperado = $valorAbertura + $dinheiroVendas + $dinheiroConvenio - $troco;
        $informado = $informado !== null ? (float) $informado : null;

        return [
            'vendas' => $linhas,
            'qtd_validas' => $qtdValidas,
            'qtd_canceladas' => $qtdCanceladas,
            'total_cancelado' => round($totalCancelado, 2),
            'qtd_restaurante' => $qtdRestaurante,
            'total_restaurante' => round($totalRestaurante, 2),
            'total_bruto' => round($totalBruto, 2),
            'total_descontos' => round($totalDescontos, 2),
            'total_liquido' => round($totalLiquido, 2),
            'formas' => collect($formas)->filter(fn ($v) => $v > 0)->map(fn ($v) => round($v, 2))->all(),
            'troco' => round($troco, 2),
            'cancelamentos' => $cancelamentos,
            'descontos' => $descontos,
            'liberacoes' => $liberacoes,
            'recebimentos_convenio' => $linhasRecebimentos,
            'convenio_recebido' => $convenioRecebido,
            'total_convenio_recebido' => round(array_sum($convenioRecebido), 2),
            'rotulos_formas' => $rotulosFormas,
            'dinheiro_vendas' => round($dinheiroVendas, 2),
            'dinheiro_convenio' => round($dinheiroConvenio, 2),
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

        return $chaves->first() ? \App\Support\FormasCaixa::rotulo($chaves->first(), $this->fonte) : '-';
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
        if ($acao === 'fechar_caixa_outro') {
            return 'Fechamento do caixa';
        }

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