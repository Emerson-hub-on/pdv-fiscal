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

class VendasDetalhadaExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithColumnFormatting
{
    private int $totalLinhas;

    public function __construct(private Collection $linhas, private object $totais)
    {
        $this->totalLinhas = $linhas->count();
    }

    public function headings(): array
    {
        return [
            'Data', 'Tipo', 'Nº documento', 'Série', 'Código', 'Descrição', 'Qtd',
            'Valor bruto', 'Descontos', 'Acréscimos', 'Valor líquido',
            'ICMS', 'PIS', 'COFINS', 'IPI', 'IBS', 'CBS', 'Vendedor',
        ];
    }

    public function array(): array
    {
        $dados = $this->linhas->map(function ($l) {
            // Item de nota antiga (emitida antes de gravar os impostos): impostos em branco, não zero
            $imposto = fn ($v) => $l->tem_impostos ? (float) $v : null;

            return [
                $l->data ? date('d/m/Y H:i', strtotime($l->data)) : '',
                $l->tipo,
                $l->numero,
                $l->serie,
                $l->codigo,
                $l->descricao,
                (float) $l->quantidade,
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
            ];
        })->all();

        $t = $this->totais;

        $dados[] = [
            'TOTAL', '', '', '', '', '', (float) $t->quantidade,
            (float) $t->valor_bruto, (float) $t->desconto, (float) $t->acrescimo, (float) $t->valor_liquido,
            (float) $t->icms, (float) $t->pis, (float) $t->cofins, (float) $t->ipi, (float) $t->ibs, (float) $t->cbs, '',
        ];

        return $dados;
    }

    public function columnFormats(): array
    {
        $dinheiro = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1;

        return [
            'G' => '#,##0.###',
            'H' => $dinheiro, 'I' => $dinheiro, 'J' => $dinheiro, 'K' => $dinheiro,
            'L' => $dinheiro, 'M' => $dinheiro, 'N' => $dinheiro, 'O' => $dinheiro, 'P' => $dinheiro, 'Q' => $dinheiro,
        ];
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
