<?php

namespace App\Services;

use App\Models\Credenciadora;
use App\Models\FormaPagamento;
use App\Models\NotaFiscal;
use App\Models\VendaPagamento;
use Illuminate\Support\Carbon;

/**
 * Totais do mês recebidos por meio eletrônico (cartão, PIX, boleto...), por credenciadora,
 * para o registro 1601. Considera a data da venda/emissão:
 *  - NFC-e autorizadas e não canceladas (pagamentos gravados no caixa)
 *  - NF-e de saída normais (finalidade 1) não canceladas, pela forma de pagamento da nota
 */
class SpedPagamentosEletronicos
{
    /**
     * @return array{porCredenciadora: array<int, array{credenciadora: Credenciadora, total: float}>, semCredenciadora: array<string, float>}
     */
    public function calcular(Carbon $inicio, Carbon $fim): array
    {
        $mapa = Credenciadora::mapaPorMeio();
        $porCredenciadora = [];
        $semCredenciadora = [];

        $somar = function (string $meio, float $valor) use ($mapa, &$porCredenciadora, &$semCredenciadora) {
            if ($valor <= 0 || !in_array($meio, Credenciadora::MEIOS_ELETRONICOS, true)) {
                return;
            }

            $credenciadora = $mapa[$meio] ?? null;

            if (!$credenciadora) {
                $rotulo = FormaPagamento::MEIOS[$meio] ?? $meio;
                $semCredenciadora[$rotulo] = round(($semCredenciadora[$rotulo] ?? 0) + $valor, 2);

                return;
            }

            $porCredenciadora[$credenciadora->id] ??= ['credenciadora' => $credenciadora, 'total' => 0.0];
            $porCredenciadora[$credenciadora->id]['total'] = round($porCredenciadora[$credenciadora->id]['total'] + $valor, 2);
        };

        // ---- Caixa (NFC-e) ----
        $pagamentosCaixa = VendaPagamento::query()
            ->whereHas('venda', fn ($q) => $q
                ->whereNotNull('chave_nfe')
                ->whereNotNull('protocolo_nfe')
                ->where('status', 'not like', '%cancel%')
                ->whereBetween('emitida_em', [$inicio, $fim]))
            ->selectRaw('forma_pagamento, SUM(valor) as total')
            ->groupBy('forma_pagamento')
            ->pluck('total', 'forma_pagamento');

        foreach ($pagamentosCaixa as $forma => $total) {
            $somar(Credenciadora::MEIO_DO_CAIXA[$forma] ?? '99', (float) $total);
        }

        // ---- NF-e de saída ----
        $notas = NotaFiscal::with(['formaPagamento', 'itens'])
            ->where('status', 'emitida')
            ->where('finalidade', 1)
            ->whereBetween('emitida_em', [$inicio, $fim])
            ->get();

        foreach ($notas as $nota) {
            // Só saída: NF-e de entrada emitida pela empresa não é recebimento
            if (SpedFiscalGerador::indicadorOperacao($nota) === '0') {
                continue;
            }

            $meio = (string) ($nota->formaPagamento?->meio_pagamento ?? '');
            $somar($meio, (float) $nota->valor_total);
        }

        return ['porCredenciadora' => $porCredenciadora, 'semCredenciadora' => $semCredenciadora];
    }
}
