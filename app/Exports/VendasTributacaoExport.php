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
 * Visão "resumida": uma linha por regime + CST/CSOSN + CFOP + alíquota de ICMS.
 * Visão "detalhada": uma linha por item vendido, com a tributação gravada na emissão.
 */
class VendasTributacaoExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithColumnFormatting
{
    private int $totalLinhas;

    public function __construct(
        private Collection $linhas,
        private object $totais,
        private string $visao = 'resumida',
        private array $descricoes = []
    ) {
        $this->totalLinhas = $linhas->count();
    }

    private function detalhada(): bool
    {
        return $this->visao === 'detalhada';
    }

    private function regime(?string $r): string
    {
        return ['simples' => 'Simples Nacional', 'normal' => 'Regime normal'][$r] ?? '—';
    }

    public function headings(): array
    {
        $impostos = ['BC ICMS', 'Alíq. ICMS', 'ICMS', 'PIS', 'COFINS', 'IPI', 'IBS', 'CBS', 'Total de impostos', '% s/ venda'];

        return $this->detalhada()
            ? array_merge(['Data', 'Tipo', 'Nº documento', 'Série', 'Código', 'Descrição', 'Regime', 'CST/CSOSN', 'CFOP', 'Valor da venda'], $impostos)
            : array_merge(['Regime', 'CST/CSOSN', 'Descrição da tributação', 'CFOP', 'Itens', 'Valor da venda'], $impostos);
    }

    public function array(): array
    {
        $t = $this->totais;
        $totalPerc = $t->valor_venda > 0 ? $t->total_impostos / $t->valor_venda : null;

        if ($this->detalhada()) {
            $dados = $this->linhas->map(function ($l) {
                $perc = (float) $l->valor_venda > 0 ? (float) $l->total_impostos / (float) $l->valor_venda : null;

                return [
                    $l->data ? date('d/m/Y H:i', strtotime($l->data)) : '',
                    $l->tipo,
                    $l->numero,
                    $l->serie,
                    $l->codigo,
                    $l->descricao,
                    $this->regime($l->regime),
                    $l->cst,
                    $l->cfop,
                    (float) $l->valor_venda,
                    (float) $l->bc_icms,
                    (float) $l->aliquota_icms / 100,
                    (float) $l->icms,
                    (float) $l->pis,
                    (float) $l->cofins,
                    (float) $l->ipi,
                    (float) $l->ibs,
                    (float) $l->cbs,
                    (float) $l->total_impostos,
                    $perc,
                ];
            })->all();

            $dados[] = [
                'TOTAL', '', '', '', '', '', '', '', '', (float) $t->valor_venda,
                (float) $t->bc_icms, '', (float) $t->icms, (float) $t->pis, (float) $t->cofins, (float) $t->ipi,
                (float) $t->ibs, (float) $t->cbs, (float) $t->total_impostos, $totalPerc,
            ];

            return $dados;
        }

        $dados = $this->linhas->map(fn ($l) => [
            $this->regime($l->regime),
            $l->cst,
            $this->descricoes[$l->cst] ?? '',
            $l->cfop,
            (int) $l->itens,
            (float) $l->valor_venda,
            (float) $l->bc_icms,
            (float) $l->aliquota_icms / 100,
            (float) $l->icms,
            (float) $l->pis,
            (float) $l->cofins,
            (float) $l->ipi,
            (float) $l->ibs,
            (float) $l->cbs,
            (float) $l->total_impostos,
            $l->carga_percentual !== null ? (float) $l->carga_percentual / 100 : null,
        ])->all();

        $dados[] = [
            'TOTAL', '', '', '', (int) $t->itens, (float) $t->valor_venda,
            (float) $t->bc_icms, '', (float) $t->icms, (float) $t->pis, (float) $t->cofins, (float) $t->ipi,
            (float) $t->ibs, (float) $t->cbs, (float) $t->total_impostos, $totalPerc,
        ];

        return $dados;
    }

    public function columnFormats(): array
    {
        $dinheiro = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1;

        // Detalhada: venda = J, BC = K, alíq. = L, impostos M..S, % = T.
        // Resumida: itens = E, venda = F, BC = G, alíq. = H, impostos I..O, % = P.
        return $this->detalhada()
            ? ['J' => $dinheiro, 'K' => $dinheiro, 'L' => '0.00%'] + array_fill_keys(range('M', 'S'), $dinheiro) + ['T' => '0.00%']
            : ['F' => $dinheiro, 'G' => $dinheiro, 'H' => '0.00%'] + array_fill_keys(range('I', 'O'), $dinheiro) + ['P' => '0.00%'];
    }

    public function styles(Worksheet $planilha): array
    {
        $ultima = $this->totalLinhas + 2; // cabeçalho + linhas + total

        return [
            1 => ['font' => ['bold' => true]],
            $ultima => ['font' => ['bold' => true]],
        ];
    }
}
