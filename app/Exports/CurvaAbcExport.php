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
 * Ranking da Curva ABC: uma linha por produto, na ordem e com os filtros da tela.
 * A posição, o % e a classe vêm do ranking completo do período (não mudam com a busca nem com o filtro de classe).
 */
class CurvaAbcExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithColumnFormatting
{
    private int $totalLinhas;

    public function __construct(private Collection $produtos, private object $resumo)
    {
        $this->totalLinhas = $produtos->count();
    }

    public function headings(): array
    {
        return ['Posição', 'Classe', 'Código', 'Produto', 'Qtd vendida', 'Nº de vendas', 'Faturamento', '% do total', '% acumulado'];
    }

    public function array(): array
    {
        $dados = $this->produtos->map(fn ($p) => [
            (int) $p->posicao,
            $p->classe,
            $p->codigo,
            $p->nome,
            (float) $p->quantidade,
            (int) $p->vendas,
            (float) $p->faturamento,
            (float) $p->percentual / 100,
            (float) $p->percentual_acumulado / 100,
        ])->all();

        // Total das linhas exportadas (com filtro de classe ou busca, é só o que está na planilha)
        $dados[] = [
            'TOTAL', '', '', '',
            (float) $this->produtos->sum('quantidade'),
            '',
            (float) $this->produtos->sum('faturamento'),
            (float) $this->produtos->sum('percentual') / 100,
            '',
        ];

        return $dados;
    }

    public function columnFormats(): array
    {
        return [
            'E' => '#,##0.###',
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'H' => '0.00%',
            'I' => '0.00%',
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
