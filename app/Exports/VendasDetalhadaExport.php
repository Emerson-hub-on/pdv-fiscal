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
 */
class VendasDetalhadaExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithColumnFormatting
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
        $valores = ['Valor bruto', 'Descontos', 'Acréscimos', 'Valor líquido', 'ICMS', 'PIS', 'COFINS', 'IPI', 'IBS', 'CBS', 'Vendedor'];

        return $this->detalhada()
            ? array_merge(['Data', 'Tipo', 'Nº documento', 'Série', 'Código', 'Descrição', 'Qtd'], $valores)
            : array_merge(['Data', 'Tipo', 'Nº documento', 'Série', 'Itens', 'Qtd'], $valores);
    }

    public function array(): array
    {
        $dados = $this->linhas->map(function ($l) {
            // Venda/item de nota antiga (emitida antes de gravar os impostos): impostos em branco, não zero
            $imposto = fn ($v) => $l->tem_impostos ? (float) $v : null;

            $inicio = [
                $l->data ? date('d/m/Y H:i', strtotime($l->data)) : '',
                $l->tipo,
                $l->numero,
                $l->serie,
            ];

            $meio = $this->detalhada()
                ? [$l->codigo, $l->descricao, (float) $l->quantidade]
                : [(int) $l->qtd_itens, (float) $l->quantidade];

            return array_merge($inicio, $meio, [
                (float) $l->valor_bruto,
                (float) $l->desconto,
                (float) $l->acrescimo,
                (float) $l->valor_liquido,
                $imposto($l->icms),
                $imposto($l->pis),
                $imposto($l->cofins),
                $imposto($l->ipi),
                $imposto($l->ibs),
                $imposto($l->cbs),
                $l->vendedor,
            ]);
        })->all();

        $t = $this->totais;

        $rotulo = $this->detalhada()
            ? ['TOTAL', '', '', '', '', '', (float) $t->quantidade]
            : ['TOTAL', '', '', '', (int) $t->itens, (float) $t->quantidade];

        $dados[] = array_merge($rotulo, [
            (float) $t->valor_bruto, (float) $t->desconto, (float) $t->acrescimo, (float) $t->valor_liquido,
            (float) $t->icms, (float) $t->pis, (float) $t->cofins, (float) $t->ipi, (float) $t->ibs, (float) $t->cbs, '',
        ]);

        return $dados;
    }

    public function columnFormats(): array
    {
        $dinheiro = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1;

        // Detalhada: Qtd = G, valores H..Q. Resumida tem uma coluna a menos antes: Qtd = F, valores G..P.
        [$qtd, $valores] = $this->detalhada()
            ? ['G', range('H', 'Q')]
            : ['F', range('G', 'P')];

        return [$qtd => '#,##0.###'] + array_fill_keys($valores, $dinheiro);
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