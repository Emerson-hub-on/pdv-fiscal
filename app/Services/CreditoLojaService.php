<?php

namespace App\Services;

use App\Models\CreditoLoja;
use App\Models\CreditoLojaMovimento;
use App\Models\Empresa;
use App\Models\NotaFiscal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Crédito em loja (haver do cliente / vale-troca).
 *
 * - Nasce quando a NF-e de ENTRADA de uma troca é autorizada, no valor da nota. Fica em nome do cliente;
 *   se a devolução foi contra a própria empresa (consumidor não identificado), vale pelo código (VT000123).
 * - É usado na NF-e de venda (pagamento "21 – Crédito em loja") ou no caixa (forma "credito_loja").
 *   Na NF-e: abatido na autorização e estornado no cancelamento.
 *   No caixa: abatido no SQLite na hora da venda e confirmado aqui quando a venda sobe (idempotente pelo uuid).
 * - Cancelar a NF-e de entrada cancela o crédito, desde que ele não tenha sido usado.
 */
class CreditoLojaService
{
    public const CODIGO_PREFIXO = 'VT';

    public static function codigoDaNota(int $notaFiscalId): string
    {
        return self::CODIGO_PREFIXO . str_pad((string) $notaFiscalId, 6, '0', STR_PAD_LEFT);
    }

    /** NF-e de entrada de troca (devolução de cliente). */
    public static function ehEntradaDeTroca(NotaFiscal $nota): bool
    {
        return $nota->origem_tipo === 'troca' && ($nota->troca_venda_id || $nota->troca_nota_fiscal_id);
    }

    // ---------------- Geração e cancelamento (NF-e de entrada da troca) ----------------

    public function gerarDaTroca(NotaFiscal $entrada): ?CreditoLoja
    {
        if (!self::ehEntradaDeTroca($entrada) || (float) $entrada->valor_total <= 0) {
            return null;
        }

        $existente = CreditoLoja::where('nota_fiscal_id', $entrada->id)->first();
        if ($existente) {
            return $existente;
        }

        $documentoEmpresa = preg_replace('/\D/', '', (string) Empresa::first()?->cnpj);
        $cliente = \App\Models\Cliente::find($entrada->cliente_id); // sempre o destinatário atual da nota
        $clienteId = ($cliente && $cliente->cpf_cnpj !== $documentoEmpresa) ? $cliente->id : null;

        $credito = CreditoLoja::create([
            'codigo'         => self::codigoDaNota($entrada->id),
            'cliente_id'     => $clienteId,
            'nota_fiscal_id' => $entrada->id,
            'valor'          => $entrada->valor_total,
            'saldo'          => $entrada->valor_total,
            'status'         => 'ativo',
            'observacao'     => $clienteId ? null : 'Consumidor não identificado: usar pelo código do vale-troca.',
        ]);

        $this->movimento($credito, 'credito', (float) $entrada->valor_total, [
            'nota_fiscal_id' => $entrada->id,
            'descricao'      => "Troca: NF-e de entrada nº {$entrada->numero}",
        ]);

        return $credito;
    }

    /** Antes de cancelar a NF-e de entrada: motivo que impede (crédito já usado), ou null. */
    public function erroCancelarTroca(NotaFiscal $entrada): ?string
    {
        $credito = CreditoLoja::where('nota_fiscal_id', $entrada->id)->first();

        if ($credito && $credito->status !== 'cancelado' && (float) $credito->saldo < (float) $credito->valor - 0.004) {
            return sprintf(
                'O crédito em loja %s gerado por esta troca já foi usado (R$ %s de R$ %s). Cancele antes a venda que usou o crédito.',
                $credito->codigo,
                number_format((float) $credito->valor - (float) $credito->saldo, 2, ',', '.'),
                number_format((float) $credito->valor, 2, ',', '.')
            );
        }

        return null;
    }

    public function cancelarDaTroca(NotaFiscal $entrada): void
    {
        $credito = CreditoLoja::where('nota_fiscal_id', $entrada->id)->lockForUpdate()->first();

        if (!$credito || $credito->status === 'cancelado') {
            return;
        }

        $this->movimento($credito, 'cancelamento', (float) $credito->saldo, [
            'nota_fiscal_id' => $entrada->id,
            'descricao'      => 'NF-e de entrada da troca cancelada',
        ]);
        $credito->update(['saldo' => 0, 'status' => 'cancelado']);
    }

    // ---------------- Consulta ----------------

    /** Créditos utilizáveis: do cliente e, numa venda de troca, o crédito gerado pela própria troca. */
    public function disponiveis(?int $clienteId, ?int $trocaEntradaId = null): Collection
    {
        if (!$clienteId && !$trocaEntradaId) {
            return collect();
        }

        return CreditoLoja::disponiveis()
            ->where(function ($q) use ($clienteId, $trocaEntradaId) {
                $q->when($clienteId, fn ($q) => $q->orWhere('cliente_id', $clienteId))
                  ->when($trocaEntradaId, fn ($q) => $q->orWhere('nota_fiscal_id', $trocaEntradaId));
            })
            ->orderBy('id')
            ->get();
    }

    public function saldoDisponivel(?int $clienteId, ?int $trocaEntradaId = null): float
    {
        return round((float) $this->disponiveis($clienteId, $trocaEntradaId)->sum('saldo'), 2);
    }

    // ---------------- NF-e de venda ----------------

    /**
     * Na emissão (dentro da transação, ANTES de ir à SEFAZ): trava os créditos e confere o saldo.
     * Retorna os créditos travados para usar() depois da autorização.
     */
    public function reservarParaNota(NotaFiscal $nota): Collection
    {
        $valor = round((float) $nota->valor_credito_loja, 2);

        if ($valor <= 0) {
            return collect();
        }

        if (!$nota->cliente_id && !$nota->troca_entrada_id) {
            throw new RuntimeException('A nota usa crédito em loja, mas não tem cliente para localizar o crédito.');
        }

        $creditos = CreditoLoja::disponiveis()
            ->where(function ($q) use ($nota) {
                $q->when($nota->cliente_id, fn ($q) => $q->orWhere('cliente_id', $nota->cliente_id))
                  ->when($nota->troca_entrada_id, fn ($q) => $q->orWhere('nota_fiscal_id', $nota->troca_entrada_id));
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $saldo = round((float) $creditos->sum('saldo'), 2);

        if ($saldo + 0.004 < $valor) {
            throw new RuntimeException(sprintf(
                'Crédito em loja insuficiente: a nota usa R$ %s e o cliente tem R$ %s disponível.',
                number_format($valor, 2, ',', '.'), number_format($saldo, 2, ',', '.')
            ));
        }

        return $creditos;
    }

    /** Depois da autorização: abate o valor dos créditos reservados (o crédito da própria troca primeiro). */
    public function usarNaNota(NotaFiscal $nota, Collection $creditos): void
    {
        $restante = round((float) $nota->valor_credito_loja, 2);

        $ordenados = $creditos->sortBy(fn ($c) => $c->nota_fiscal_id === $nota->troca_entrada_id ? 0 : 1)->values();

        foreach ($ordenados as $credito) {
            if ($restante <= 0) {
                break;
            }

            $usar = round(min($restante, (float) $credito->saldo), 2);
            $this->abater($credito, $usar, [
                'nota_fiscal_id' => $nota->id,
                'descricao'      => "Pagamento da NF-e nº {$nota->numero}",
            ]);
            $restante = round($restante - $usar, 2);
        }
    }

    /** NF-e de venda cancelada: devolve o crédito usado. */
    public function estornarNota(NotaFiscal $nota): void
    {
        $usos = CreditoLojaMovimento::where('nota_fiscal_id', $nota->id)->where('tipo', 'uso')->get();

        foreach ($usos as $uso) {
            $jaEstornado = CreditoLojaMovimento::where('credito_loja_id', $uso->credito_loja_id)
                ->where('nota_fiscal_id', $nota->id)->where('tipo', 'estorno')->exists();

            if (!$jaEstornado) {
                $this->devolver(CreditoLoja::lockForUpdate()->find($uso->credito_loja_id), (float) $uso->valor, [
                    'nota_fiscal_id' => $nota->id,
                    'descricao'      => "NF-e nº {$nota->numero} cancelada",
                ]);
            }
        }
    }

    // ---------------- Venda do caixa (NFC-e) ----------------

    /** Venda do caixa chegou ao servidor: registra o uso (uma vez por venda). */
    public function usarNaVenda(int $creditoId, float $valor, string $vendaUuid, ?int $userId = null): void
    {
        $credito = CreditoLoja::lockForUpdate()->find($creditoId);

        if (!$credito || $valor <= 0) {
            return;
        }

        if (CreditoLojaMovimento::where('credito_loja_id', $creditoId)->where('tipo', 'uso')->where('venda_uuid', $vendaUuid)->exists()) {
            return;
        }

        if ((float) $credito->saldo + 0.004 < $valor || $credito->status === 'cancelado') {
            // O caixa já vendeu (pode ter sido offline): registra mesmo assim e avisa no log
            Log::warning("Crédito em loja {$credito->codigo} usado acima do saldo/cancelado pela venda {$vendaUuid}", [
                'saldo' => (float) $credito->saldo, 'valor' => $valor, 'status' => $credito->status,
            ]);
        }

        $this->abater($credito, round($valor, 2), [
            'venda_uuid' => $vendaUuid,
            'user_id'    => $userId,
            'descricao'  => 'Pagamento de venda no caixa (NFC-e)',
        ], permitirNegativo: true);
    }

    /** Venda do caixa cancelada: devolve o crédito usado nela (uma vez). */
    public function estornarVenda(string $vendaUuid): void
    {
        $usos = CreditoLojaMovimento::where('venda_uuid', $vendaUuid)->where('tipo', 'uso')->get();

        foreach ($usos as $uso) {
            $jaEstornado = CreditoLojaMovimento::where('credito_loja_id', $uso->credito_loja_id)
                ->where('venda_uuid', $vendaUuid)->where('tipo', 'estorno')->exists();

            if (!$jaEstornado) {
                $this->devolver(CreditoLoja::lockForUpdate()->find($uso->credito_loja_id), (float) $uso->valor, [
                    'venda_uuid' => $vendaUuid,
                    'descricao'  => 'Venda do caixa (NFC-e) cancelada',
                ]);
            }
        }
    }

    // ---------------- Internos ----------------

    private function abater(CreditoLoja $credito, float $valor, array $dados, bool $permitirNegativo = false): void
    {
        if ($valor <= 0) {
            return;
        }

        $saldo = round((float) $credito->saldo - $valor, 2);
        if ($saldo < 0 && !$permitirNegativo) {
            throw new RuntimeException("Crédito em loja {$credito->codigo} sem saldo suficiente.");
        }

        $this->movimento($credito, 'uso', $valor, $dados);
        $credito->update(['saldo' => $saldo, 'status' => $credito->status === 'cancelado' ? 'cancelado' : ($saldo <= 0.004 ? 'esgotado' : 'ativo')]);
    }

    private function devolver(?CreditoLoja $credito, float $valor, array $dados): void
    {
        if (!$credito || $valor <= 0) {
            return;
        }

        $this->movimento($credito, 'estorno', $valor, $dados);
        $saldo = round((float) $credito->saldo + $valor, 2);
        $credito->update(['saldo' => $saldo, 'status' => $credito->status === 'cancelado' ? 'cancelado' : 'ativo']);
    }

    private function movimento(CreditoLoja $credito, string $tipo, float $valor, array $dados = []): void
    {
        CreditoLojaMovimento::create([
            'credito_loja_id' => $credito->id,
            'tipo'            => $tipo,
            'valor'           => round($valor, 2),
            'user_id'         => $dados['user_id'] ?? auth()->id(),
        ] + $dados);
    }
}
