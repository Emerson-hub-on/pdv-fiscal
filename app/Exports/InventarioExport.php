<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Planilha do inventário. Mesma ordem de colunas da tela, mais Referência e Status no fim.
 *
 *  A Código | B Cód. Barras | C Nome | D Und | E Custo | F Preço | G Estoque | H Marca
 *  I Categoria | J NCM | K Mínimo | L Situação | M Marg. % | N Total Custo | O Total Venda
 *  P Referência | Q Status
 */
class InventarioExport implements FromCollection, WithHeadings, WithMapping, WithColumnFormatting, WithColumnWidths, WithStyles, WithTitle, WithEvents
{
    private const MOEDA = '"R$" #,##0.00';
    private const ULTIMA_COLUNA = 'Q';

    public function __construct(private Collection $itens)
    {
    }

    public function collection(): Collection
    {
        return $this->itens;
    }

    public function headings(): array
    {
        return [
            'Código', 'Cód. Barras', 'Nome', 'Und', 'Custo', 'Preço', 'Estoque', 'Marca',
            'Categoria', 'NCM', 'Mínimo', 'Situação', 'Marg. %', 'Total Custo', 'Total Venda',
            'Referência', 'Status',
        ];
    }

    public function map($item): array
    {
        $situacoes = [
            'ok' => 'OK',
            'abaixo' => 'Abaixo do mínimo',
            'zerado' => 'Zerado',
            'negativo' => 'Negativo',
        ];

        $nome = $item->descricao . ($item->variacao ? " ({$item->variacao})" : '');
        $estoquePositivo = max((float) $item->estoque, 0);

        return [
            (string) $item->codigo_interno,
            $item->codigo_barras !== null ? (string) $item->codigo_barras : null,
            $nome,
            $item->unidade,
            (float) $item->custo,
            (float) $item->venda,
            (float) $item->estoque,
            $item->marca,
            $item->categoria,
            $item->ncm !== null ? (string) $item->ncm : null,
            (float) $item->estoque_minimo,
            $situacoes[$item->situacao] ?? $item->situacao,
            $item->margem !== null ? (float) $item->margem / 100 : null, // formato de % do Excel
            $estoquePositivo * (float) $item->custo,
            $estoquePositivo * (float) $item->venda,
            $item->referencia,
            $item->ativo ? 'Ativo' : 'Inativo',
        ];
    }

    public function columnFormats(): array
    {
        return [
            // texto: evita o Excel transformar EAN em 7,89E+12 e tirar zeros à esquerda
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'J' => NumberFormat::FORMAT_TEXT,
            'E' => self::MOEDA,
            'F' => self::MOEDA,
            'M' => NumberFormat::FORMAT_PERCENTAGE_00,
            'N' => self::MOEDA,
            'O' => self::MOEDA,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10, 'B' => 16, 'C' => 45, 'D' => 7, 'E' => 13, 'F' => 13, 'G' => 11, 'H' => 18,
            'I' => 18, 'J' => 12, 'K' => 11, 'L' => 18, 'M' => 10, 'N' => 15, 'O' => 15,
            'P' => 16, 'Q' => 10,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFBEB']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];
    }

    public function title(): string
    {
        return 'Inventário';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $ultima = $sheet->getHighestRow();
                $ate = self::ULTIMA_COLUNA;

                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:{$ate}{$ultima}");

                if ($ultima < 2) {
                    return;
                }

                // Linha de totais, fora do filtro, com fórmulas (o Excel recalcula se o operador editar)
                $total = $ultima + 1;
                $sheet->setCellValue("C{$total}", 'TOTAIS');
                $sheet->setCellValue("G{$total}", "=SUM(G2:G{$ultima})");
                $sheet->setCellValue("N{$total}", "=SUM(N2:N{$ultima})");
                $sheet->setCellValue("O{$total}", "=SUM(O2:O{$ultima})");

                $linha = $sheet->getStyle("A{$total}:{$ate}{$total}");
                $linha->getFont()->setBold(true);
                $linha->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);

                $sheet->getStyle("N{$total}:O{$total}")->getNumberFormat()->setFormatCode(self::MOEDA);
            },
        ];
    }
}
