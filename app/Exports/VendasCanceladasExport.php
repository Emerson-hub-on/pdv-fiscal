<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Relatório de vendas canceladas.
 * Resumida: uma linha por venda cancelada. Detalhada: uma linha por item, repetindo os dados da venda;
 * o valor da venda só vai na 1ª linha de cada uma (a soma da coluna não duplica).
 */
class VendasCanceladasExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithColumnFormatting
{
    private int $totalLinhas = 0;

    public function __construct(
        private Collection $documentos,
        private Collection $itensPorDocumento,
        private object $totais,
        private string $visao = 'resumida',
    ) {
    }

    private function detalhada(): bool
    {
        return $this->visao === 'detalhada';
    }

    public function headings(): array
    {
        $venda = ['Cancelada em', 'Tipo', 'Nº documento', 'Série', 'Venda em', 'Cliente', 'Valor da venda', 'Motivo',
                  'Cancelado por', 'Liberado por', 'Protocolo do cancelamento', 'Vendedor'];

        return $this->detalhada()
            ? array_merge($venda, ['Código', 'Descrição', 'Qtd', 'Valor unit.', 'Desconto', 'Total do item'])
            : array_merge($venda, ['Itens']);
    }

    public function array(): array
    {
        $data = fn ($v) => $v ? date('d/m/Y H:i', strtotime($v)) : '';
        $linhas = [];

        foreach ($this->documentos as $d) {
            $venda = [
                $data($d->cancelado_em), $d->tipo, $d->numero, $d->serie, $data($d->emitida_em),
                $d->cliente ?: 'Consumidor', (float) $d->valor, $d->motivo,
                $d->cancelado_por, $d->autorizado_por === 'Operador liberado' ? 'Próprio operador (liberado)' : $d->autorizado_por,
                $d->protocolo_cancelamento, $d->vendedor,
            ];

            if (!$this->detalhada()) {
                $linhas[] = array_merge($venda, [(int) $d->qtd_itens]);
                continue;
            }

            $itens = $this->itensPorDocumento[$d->tipo . '-' . $d->documento_id] ?? collect();
            if ($itens->isEmpty()) {
                $linhas[] = array_merge($venda, ['', '', null, null, null, null]);
                continue;
            }

            foreach ($itens->values() as $i => $item) {
                $linha = $venda;
                if ($i > 0) {
                    $linha[6] = null; // valor da venda só na 1ª linha
                }
                $linhas[] = array_merge($linha, [
                    $item->codigo, $item->descricao, (float) $item->quantidade,
                    (float) $item->valor_unitario, (float) $item->desconto, (float) $item->valor_total,
                ]);
            }
        }

        $this->totalLinhas = count($linhas);
        $total = ['TOTAL', '', '', '', '', number_format($this->totais->documentos, 0, ',', '.') . ' venda(s)', (float) $this->totais->valor, '', '', '', '', ''];
        $linhas[] = $this->detalhada() ? array_merge($total, ['', '', null, null, null, null]) : array_merge($total, [(int) $this->totais->itens]);

        return $linhas;
    }

    public function columnFormats(): array
    {
        $dinheiro = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1;

        return $this->detalhada()
            ? ['G' => $dinheiro, 'O' => '#,##0.###', 'P' => $dinheiro, 'Q' => $dinheiro, 'R' => $dinheiro]
            : ['G' => $dinheiro];
    }

    public function styles(Worksheet $planilha): array
    {
        return [
            1 => ['font' => ['bold' => true]],
            $this->totalLinhas + 2 => ['font' => ['bold' => true]],
        ];
    }
}
