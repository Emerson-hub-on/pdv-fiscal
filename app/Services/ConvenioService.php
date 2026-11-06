<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ContaReceber;
use App\Models\ContaReceberParcela;
use App\Models\LimiteCreditoHistorico;
use App\Models\TipoContaReceber;
use App\Models\Venda;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Convênio (venda fiado): o cliente compra usando o limite de crédito concedido no cadastro.
 *
 * - Cada venda paga no convênio vira uma conta a receber (origem "convenio", ligada à venda),
 *   com parcela única vencendo em "limite_credito_dias" dias. A baixa é feita no Contas a receber.
 * - Limite usado = soma das parcelas dessas contas ainda não recebidas; disponível = limite − usado.
 * - Na NFC-e o pagamento sai como tPag 05 (Crédito Loja) com indPag 1 (a prazo): a forma com que
 *   o cliente quitar a dívida depois não muda o documento fiscal, é só um recebimento financeiro.
 */
class ConvenioService
{
    public const ORIGEM = 'convenio';

    /** Limite em uso: parcelas em aberto das contas de convênio do cliente. */
    public function usado(int $clienteId): float
    {
        return round((float) ContaReceberParcela::whereNull('data_recebimento')
            ->whereHas('conta', fn ($q) => $q->where('origem', self::ORIGEM)->where('cliente_id', $clienteId))
            ->sum('valor'), 2);
    }

    /** [limite, usado, disponivel, ativo, dias] do cliente. */
    public function resumo(Cliente $cliente): array
    {
        $limite = $cliente->temConvenio() ? (float) $cliente->limite_credito : 0.0;
        $usado = $this->usado($cliente->id);

        return [
            'ativo'      => $cliente->temConvenio(),
            'limite'     => round($limite, 2),
            'usado'      => $usado,
            'disponivel' => round(max(0, $limite - $usado), 2),
            'dias'       => (int) ($cliente->limite_credito_dias ?: 30),
        ];
    }

    /** Grava o limite do cliente e registra a alteração no histórico (só quando algo mudou). */
    public function definirLimite(Cliente $cliente, bool $ativo, float $limite, int $dias, ?string $observacao, ?int $userId): void
    {
        $limite = round(max(0, $limite), 2);
        $dias = max(1, $dias);

        $mudou = $cliente->limite_credito_ativo !== $ativo
            || abs((float) $cliente->limite_credito - $limite) > 0.009
            || (int) $cliente->limite_credito_dias !== $dias
            || (string) $cliente->limite_credito_observacao !== (string) $observacao;

        if (!$mudou) {
            return;
        }

        $anterior = (float) $cliente->limite_credito;

        $cliente->forceFill([
            'limite_credito_ativo'      => $ativo,
            'limite_credito'            => $limite,
            'limite_credito_dias'       => $dias,
            'limite_credito_observacao' => $observacao,
        ])->save();

        LimiteCreditoHistorico::create([
            'cliente_id'      => $cliente->id,
            'ativo'           => $ativo,
            'limite_anterior' => $anterior,
            'limite_novo'     => $limite,
            'dias'            => $dias,
            'observacao'      => $observacao,
            'user_id'         => $userId,
        ]);
    }

    public function tipoConta(): TipoContaReceber
    {
        return TipoContaReceber::firstOrCreate(['descricao' => 'Convênio'], ['ativo' => true]);
    }

    public static function numeroDocumento(?Venda $venda): ?string
    {
        if (!$venda) {
            return null;
        }

        return $venda->numero_nfce
            ? 'NFC-e ' . $venda->numero_nfce . ($venda->serie_nfce ? '/' . $venda->serie_nfce : '')
            : 'Venda #' . $venda->id;
    }

    /**
     * Venda do caixa paga (no todo ou em parte) no convênio: cria a conta a receber.
     * Idempotente pela venda: rodar de novo (sincronização repetida) não duplica a dívida.
     */
    public function lancarDaVenda(int $vendaId, float $valor, $dataVenda = null): ?ContaReceber
    {
        $valor = round($valor, 2);
        if ($valor <= 0) {
            return null;
        }

        $existente = ContaReceber::where('venda_id', $vendaId)->where('origem', self::ORIGEM)->first();
        if ($existente) {
            return $existente;
        }

        $venda = Venda::findOrFail($vendaId);
        if (!$venda->cliente_id) {
            throw new \RuntimeException("A venda #{$vendaId} foi paga no convênio sem cliente identificado.");
        }

        $cliente = Cliente::find($venda->cliente_id);
        $data = Carbon::parse($dataVenda ?? $venda->created_at ?? now());
        $dias = (int) ($cliente?->limite_credito_dias ?: 30);

        $conta = ContaReceber::create([
            'tipo_conta_receber_id' => $this->tipoConta()->id,
            'cliente_id'            => $venda->cliente_id,
            'venda_id'              => $vendaId,
            'origem'                => self::ORIGEM,
            'descricao'             => 'Convênio — ' . self::numeroDocumento($venda),
            'numero_documento'      => self::numeroDocumento($venda),
            'data_conta'            => $data->toDateString(),
            'valor_total'           => $valor,
            'observacao'            => 'Venda no convênio (fiado) feita no caixa.',
            'status'                => 'aberta',
        ]);

        $conta->parcelas()->create([
            'numero'     => 1,
            'vencimento' => $data->copy()->addDays($dias)->toDateString(),
            'valor'      => $valor,
        ]);

        \App\Models\ContaReceberHistorico::registrar($conta, 'criacao', [
            'origem' => 'caixa', 'valor' => $valor, 'user_id' => $venda->operador_id,
            'descricao' => 'Venda no convênio (' . self::numeroDocumento($venda) . '), vencimento em ' . $dias . ' dias',
            'ocorrido_em' => $data,
        ]);

        // Venda acima do limite (ex.: dois caixas offline usando o mesmo limite): registra, não bloqueia a venda já feita
        if ($cliente && $this->usado($cliente->id) - (float) $cliente->limite_credito > 0.009) {
            Log::warning("Convênio do cliente #{$cliente->id} passou do limite com a venda #{$vendaId}.");
        }

        return $conta;
    }

    /** Número da NFC-e chegou depois da venda (emissão no caixa): atualiza a identificação da dívida. */
    public function atualizarDocumento(int $vendaId): void
    {
        $conta = ContaReceber::where('venda_id', $vendaId)->where('origem', self::ORIGEM)->first();
        $numero = self::numeroDocumento(Venda::find($vendaId));

        if ($conta && $numero && $conta->numero_documento !== $numero) {
            $conta->update(['numero_documento' => $numero, 'descricao' => 'Convênio — ' . $numero]);
        }
    }

    /**
     * Venda cancelada: a dívida do convênio deixa de existir. Sem recebimento, a conta é excluída;
     * com parcela já recebida, só as pendentes saem (o valor recebido fica para o operador devolver).
     */
    public function estornarVenda(string $vendaUuid): void
    {
        $vendaId = Venda::where('uuid', $vendaUuid)->value('id');
        if (!$vendaId) {
            return;
        }

        DB::transaction(function () use ($vendaId) {
            $conta = ContaReceber::where('venda_id', $vendaId)->where('origem', self::ORIGEM)->lockForUpdate()->first();
            if (!$conta) {
                return;
            }

            if (!$conta->parcelas()->whereNotNull('data_recebimento')->exists()) {
                $conta->delete();
                return;
            }

            $aberto = (float) $conta->parcelas()->whereNull('data_recebimento')->sum('valor');
            $conta->parcelas()->whereNull('data_recebimento')->delete();
            $conta->update(['observacao' => trim($conta->observacao . "\nVenda cancelada: as parcelas em aberto foram excluídas.")]);
            \App\Models\ContaReceberHistorico::registrar($conta, 'cancelamento', [
                'origem' => 'sistema', 'valor' => $aberto,
                'descricao' => 'NFC-e cancelada: as parcelas em aberto foram excluídas. O valor já recebido precisa ser devolvido ao cliente.',
            ]);
            $conta->recalcularStatus();
            Log::warning("Venda #{$vendaId} cancelada com parcela de convênio já recebida (conta #{$conta->id}).");
        });
    }

    /** Forma do caixa → meio de pagamento (tPag) da forma de pagamento do financeiro. */
    private const MEIOS_DO_CAIXA = [
        'dinheiro' => ['01'],
        'pix'      => ['17', '20'],
        'credito'  => ['03'],
        'debito'   => ['04'],
    ];

    public const FORMAS_RECEBIMENTO = [
        'dinheiro' => 'Dinheiro',
        'pix'      => 'PIX',
        'credito'  => 'Cartão de crédito',
        'debito'   => 'Cartão de débito',
    ];

    /**
     * Baixa do convênio feita no caixa (sobe pela sincronização). Idempotente pelo uuid do recebimento.
     * Abate das parcelas em aberto pela ordem de vencimento; pagamento menor que a parcela a divide em
     * uma parte recebida e o restante em aberto, no mesmo vencimento.
     * Se o painel já tiver baixado parte da dívida enquanto o caixa estava offline, o que passar do saldo
     * fica registrado na observação da conta (o dinheiro está na gaveta do caixa).
     */
    public function receberDoCaixa(array $r): void
    {
        if (ContaReceberParcela::where('recebimento_uuid', $r['uuid'])->exists()) {
            return;
        }

        $vendaId = Venda::where('uuid', $r['venda_uuid'])->value('id');
        if (!$vendaId) {
            throw new \RuntimeException('A venda deste convênio ainda não está no servidor.');
        }

        DB::transaction(function () use ($r, $vendaId) {
            $conta = ContaReceber::where('venda_id', $vendaId)->where('origem', self::ORIGEM)->lockForUpdate()->first();
            if (!$conta) {
                throw new \RuntimeException('Dívida de convênio não encontrada no servidor (venda cancelada?).');
            }

            $data = Carbon::parse($r['recebido_em'] ?? now())->toDateString();
            $formaId = \App\Models\FormaPagamento::ativos()
                ->whereIn('meio_pagamento', self::MEIOS_DO_CAIXA[$r['forma_pagamento']] ?? ['99'])
                ->orderBy('id')->value('id');
            $marca = [
                'data_recebimento'     => $data,
                'forma_pagamento_id'   => $formaId,
                'recebimento_uuid'     => $r['uuid'],
                'recebido_caixa_uuid'  => $r['caixa_uuid'] ?? null,
                'recebido_forma_caixa' => $r['forma_pagamento'],
                'recebido_por_id'      => $r['operador_id'] ?? null,
            ];

            $restante = round((float) $r['valor'], 2);
            $historico = [];

            foreach ($conta->parcelas()->whereNull('data_recebimento')->orderBy('vencimento')->orderBy('id')->get() as $parcela) {
                if ($restante <= 0.009) {
                    break;
                }

                $valorParcela = (float) $parcela->valor;

                if ($restante + 0.009 >= $valorParcela) {
                    $parcela->update($marca + ['valor_recebido' => $valorParcela]);
                    $historico[] = [$parcela->fresh(), $valorParcela, 'Baixa total da parcela'];
                    $restante = round($restante - $valorParcela, 2);
                    continue;
                }

                // Pagamento parcial: a parcela fica com o restante e nasce uma parcela recebida com o valor pago
                $parcela->update(['valor' => round($valorParcela - $restante, 2)]);
                $historico[] = [null, $restante, 'Pagamento parcial: ficaram R$ ' . number_format($valorParcela - $restante, 2, ',', '.') . ' em aberto'];
                $conta->parcelas()->create($marca + [
                    'numero'         => 0,
                    'vencimento'     => $parcela->vencimento,
                    'valor'          => $restante,
                    'valor_recebido' => $restante,
                ]);
                $restante = 0.0;
            }

            if ($restante > 0.009) {
                $conta->update(['observacao' => trim($conta->observacao . "\nRecebido no caixa R$ "
                    . number_format($restante, 2, ',', '.') . " além do saldo em {$data} (a dívida já tinha sido baixada).")]);
                Log::warning("Convênio da conta #{$conta->id}: caixa recebeu R$ {$restante} além do saldo.");
            }

            $conta->parcelas()->orderBy('vencimento')->orderBy('id')->get()
                ->each(fn ($p, $i) => $p->update(['numero' => $i + 1]));
            $conta->recalcularStatus();

            $forma = self::FORMAS_RECEBIMENTO[$r['forma_pagamento']] ?? $r['forma_pagamento'];
            foreach ($historico as [$parcela, $valor, $texto]) {
                // parcela recebida (a dividida aparece pela parte recebida, já renumerada)
                $parcela ??= $conta->parcelas()->where('recebimento_uuid', $r['uuid'])->orderByDesc('id')->first();
                \App\Models\ContaReceberHistorico::registrar($conta, 'baixa', [
                    'origem' => 'caixa', 'valor' => $valor, 'forma' => $forma, 'user_id' => $r['operador_id'] ?? null,
                    'descricao' => $texto . '. Recebido no caixa.', 'ocorrido_em' => $r['recebido_em'] ?? now(),
                ], $parcela?->fresh());
            }
            if ($restante > 0.009) {
                \App\Models\ContaReceberHistorico::registrar($conta, 'baixa', [
                    'origem' => 'caixa', 'valor' => $restante, 'forma' => $forma, 'user_id' => $r['operador_id'] ?? null,
                    'descricao' => 'Recebido no caixa além do saldo (a dívida já tinha sido baixada).', 'ocorrido_em' => $r['recebido_em'] ?? now(),
                ]);
            }
        });
    }

    /** Dívidas de convênio em aberto (para o cache do caixa). */
    public function abertos()
    {
        return ContaReceber::with(['cliente:id,nome', 'venda:id,uuid,numero_nfce,serie_nfce,created_at', 'parcelas'])
            ->where('origem', self::ORIGEM)
            ->where('status', 'aberta')
            ->orderBy('id')
            ->get();
    }
}
