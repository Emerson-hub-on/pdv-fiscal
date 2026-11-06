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

            $conta->parcelas()->whereNull('data_recebimento')->delete();
            $conta->update(['observacao' => trim($conta->observacao . "\nVenda cancelada: as parcelas em aberto foram excluídas.")]);
            $conta->recalcularStatus();
            Log::warning("Venda #{$vendaId} cancelada com parcela de convênio já recebida (conta #{$conta->id}).");
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
