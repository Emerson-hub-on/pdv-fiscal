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
 * Visão "detalhada": uma linha por item vendido.
 * Visão "resumida": uma linha por venda (NFC-e ou NF-e), com os valores somados dos itens.
 * Venda/item sem custo ou impostos gravados sai com custo, carga, lucro e margem em branco.
 */
class MargemLucroExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithColumnFormatting
{
    private int $totalLinhas;

    public function __construct(private Collection $linhas, private object $totais, private string $visao = 'resumida')
    {
        $this->totalLinhas = $linhas->count();
    }

    private function detalhada(): bool
    {
        return $this->visao === 'detalhada';
    }

    public function headings(): array
    {
        return $this->detalhada()
            ? ['Data', 'Tipo', 'Nº documento', 'Série', 'Código', 'Descrição', 'Qtd', 'Custo unit.', 'Valor da venda', 'Custo total', 'ICMS', 'PIS', 'COFINS', 'Carga tributária', 'Lucro', 'Margem', 'Vendedor']
            : ['Data', 'Tipo', 'Nº documento', 'Série', 'Itens', 'Qtd', 'Valor da venda', 'Custo total', 'Carga tributária', 'Lucro', 'Margem', 'Vendedor'];
    }

    public function array(): array
    {
        $dados = $this->linhas->map(function ($l) {
            // Sem custo/impostos gravados: em branco, não zero (zero pareceria 100% de margem)
            $completo = (bool) $l->completo;
            $s = fn ($v) => $completo ? (float) $v : null;

            $inicio = [
                $l->data ? date('d/m/Y H:i', strtotime($l->data)) : '',
                $l->tipo,
                $l->numero,
                $l->serie,
            ];

            $margem = ($completo && $l->margem !== null) ? (float) $l->margem / 100 : null;

            if ($this->detalhada()) {
                return array_merge($inicio, [
                    $l->codigo,
                    $l->descricao,
                    (float) $l->quantidade,
                    $l->custo_unitario !== null ? (float) $l->custo_unitario : null,
                    (float) $l->valor_venda,
                    $l->custo !== null ? (float) $l->custo : null,
                    $l->tem_impostos ? (float) $l->icms : null,
                    $l->tem_impostos ? (float) $l->pis : null,
                    $l->tem_impostos ? (float) $l->cofins : null,
                    $l->tem_impostos ? (float) $l->carga : null,
                    $s($l->lucro),
                    $margem,
                    $l->vendedor,
                ]);
            }

            return array_merge($inicio, [
                (int) $l->qtd_itens,
                (float) $l->quantidade,
                (float) $l->valor_venda,
                $s($l->custo),
                $s($l->carga),
                $s($l->lucro),
                $margem,
                $l->vendedor,
            ]);
        })->all();

        $t = $this->totais;
        $margem = $t->margem !== null ? (float) $t->margem / 100 : null;

        $dados[] = $this->detalhada()
            ? ['TOTAL', '', '', '', '', '', (float) $t->quantidade, '', (float) $t->valor_venda, (float) $t->custo, (float) $t->icms, (float) $t->pis, (float) $t->cofins, (float) $t->carga, (float) $t->lucro, $margem, '']
            : ['TOTAL', '', '', '', (int) $t->itens, (float) $t->quantidade, (float) $t->valor_venda, (float) $t->custo, (float) $t->carga, (float) $t->lucro, $margem, ''];

        return $dados;
    }

    public function columnFormats(): array
    {
        $dinheiro = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1;
        $percentual = '0.00%';

        // Detalhada: Qtd = G, Custo unit. = H, valores I..O, Margem = P.
        // Resumida: Qtd = F, valores G..J, Margem = K.
        return $this->detalhada()
            ? ['G' => '#,##0.###', 'H' => '#,##0.0000'] + array_fill_keys(range('I', 'O'), $dinheiro) + ['P' => $percentual]
            : ['F' => '#,##0.###'] + array_fill_keys(range('G', 'J'), $dinheiro) + ['K' => $percentual];
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
