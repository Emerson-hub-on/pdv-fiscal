<?php

namespace App\Exports;

use Carbon\Carbon;
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
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Visão "nota" (item a item):
 *  A Código | B Cód. Barras | C Nome | D Und | E Nota | F Série | G Fornecedor | H CNPJ/CPF
 *  I Entrada | J Emissão | K Qtd nota | L Qtd/cx | M Qtd recebida | N Vl. unit. | O Desconto
 *  P Total | Q Custo un. | R Operação | S Status | T Chave de acesso
 *
 * Visão "produto" (consolidado):
 *  A Código | B Cód. Barras | C Nome | D Und | E Notas | F Fornecedores | G Qtd recebida
 *  H Valor movimentado | I Custo médio un. | J Estoque atual | K Primeira entrada | L Última entrada
 */
class EntradaProdutosExport implements FromCollection, WithHeadings, WithMapping, WithColumnFormatting, WithColumnWidths, WithStyles, WithTitle, WithEvents
{
    private const MOEDA = '"R$" #,##0.00';
    private const MOEDA_UNIT = '"R$" #,##0.0000';

    private const STATUS = ['finalizada' => 'Finalizada', 'rascunho' => 'Rascunho', 'cancelada' => 'Cancelada'];

    public function __construct(private Collection $itens, private string $visao)
    {
    }

    private function porProduto(): bool
    {
        return $this->visao === 'produto';
    }

    public function collection(): Collection
    {
        return $this->itens;
    }

    public function headings(): array
    {
        return $this->porProduto()
            ? [
                'Código', 'Cód. Barras', 'Nome', 'Und', 'Notas', 'Fornecedores', 'Qtd recebida',
                'Valor movimentado', 'Custo médio un.', 'Estoque atual', 'Primeira entrada', 'Última entrada',
            ]
            : [
                'Código', 'Cód. Barras', 'Nome', 'Und', 'Nota', 'Série', 'Fornecedor', 'CNPJ/CPF',
                'Entrada', 'Emissão', 'Qtd nota', 'Qtd/cx', 'Qtd recebida', 'Vl. unit.', 'Desconto',
                'Total', 'Custo un.', 'Operação', 'Status', 'Chave de acesso',
            ];
    }

    public function map($item): array
    {
        return $this->porProduto() ? $this->linhaProduto($item) : $this->linhaNota($item);
    }

    private function linhaNota($i): array
    {
        $nome = $i->descricao . ($i->variacao ? " ({$i->variacao})" : '');

        return [
            $this->texto($i->codigo_interno),
            $this->texto($i->codigo_barras),
            $nome,
            $i->unidade,
            $this->texto($i->numero),
            $this->texto($i->serie),
            $i->fornecedor,
            $this->texto($i->fornecedor_doc),
            $this->dataExcel($i->data_entrada),
            $this->dataExcel($i->data_emissao),
            (float) $i->quantidade,
            (int) $i->qtd_por_caixa,
            (float) $i->quantidade_estoque,
            (float) $i->valor_unitario,
            (float) $i->valor_desconto,
            (float) $i->valor_total,
            $i->custo_unitario !== null ? (float) $i->custo_unitario : null,
            $i->operacao . ($i->movimenta_estoque ? '' : ' (não movimenta estoque)'),
            self::STATUS[$i->status] ?? $i->status,
            $this->texto($i->chave_acesso),
        ];
    }

    private function linhaProduto($i): array
    {
        $nome = $i->descricao . ($i->variacao ? " ({$i->variacao})" : '');

        return [
            $this->texto($i->codigo_interno),
            $this->texto($i->codigo_barras),
            $nome,
            $i->unidade,
            (int) $i->notas,
            (int) $i->fornecedores,
            (float) $i->qtd_recebida,
            (float) $i->valor_movimentado,
            $i->custo_medio !== null ? (float) $i->custo_medio : null,
            $i->estoque_atual !== null ? (float) $i->estoque_atual : null,
            $this->dataExcel($i->primeira_entrada),
            $this->dataExcel($i->ultima_entrada),
        ];
    }

    private function texto($valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }

    private function dataExcel($valor): ?float
    {
        return $valor ? Date::PHPToExcel(Carbon::parse($valor)) : null;
    }

    public function columnFormats(): array
    {
        $data = NumberFormat::FORMAT_DATE_DDMMYYYY;
        $texto = NumberFormat::FORMAT_TEXT;

        // texto: evita o Excel transformar EAN/chave em notação científica e tirar zeros à esquerda
        return $this->porProduto()
            ? [
                'A' => $texto, 'B' => $texto,
                'H' => self::MOEDA, 'I' => self::MOEDA_UNIT,
                'K' => $data, 'L' => $data,
            ]
            : [
                'A' => $texto, 'B' => $texto, 'E' => $texto, 'F' => $texto, 'H' => $texto, 'T' => $texto,
                'I' => $data, 'J' => $data,
                'N' => self::MOEDA_UNIT, 'O' => self::MOEDA, 'P' => self::MOEDA, 'Q' => self::MOEDA_UNIT,
            ];
    }

    public function columnWidths(): array
    {
        return $this->porProduto()
            ? [
                'A' => 10, 'B' => 16, 'C' => 45, 'D' => 7, 'E' => 9, 'F' => 13, 'G' => 14,
                'H' => 18, 'I' => 16, 'J' => 13, 'K' => 16, 'L' => 16,
            ]
            : [
                'A' => 10, 'B' => 16, 'C' => 45, 'D' => 7, 'E' => 11, 'F' => 7, 'G' => 32, 'H' => 20,
                'I' => 12, 'J' => 12, 'K' => 10, 'L' => 8, 'M' => 14, 'N' => 14, 'O' => 12,
                'P' => 14, 'Q' => 14, 'R' => 30, 'S' => 12, 'T' => 46,
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
        return $this->porProduto() ? 'Entradas por produto' : 'Entradas por nota';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $ultima = $sheet->getHighestRow();
                $ate = $this->porProduto() ? 'L' : 'T';

                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:{$ate}{$ultima}");

                if ($ultima < 2) {
                    return;
                }

                // Linha de totais, fora do filtro, com fórmulas
                $total = $ultima + 1;
                $sheet->setCellValue("C{$total}", 'TOTAIS');

                if ($this->porProduto()) {
                    $sheet->setCellValue("G{$total}", "=SUM(G2:G{$ultima})");
                    $sheet->setCellValue("H{$total}", "=SUM(H2:H{$ultima})");
                    $sheet->getStyle("H{$total}")->getNumberFormat()->setFormatCode(self::MOEDA);
                } else {
                    $sheet->setCellValue("M{$total}", "=SUM(M2:M{$ultima})");
                    $sheet->setCellValue("O{$total}", "=SUM(O2:O{$ultima})");
                    $sheet->setCellValue("P{$total}", "=SUM(P2:P{$ultima})");
                    $sheet->getStyle("O{$total}:P{$total}")->getNumberFormat()->setFormatCode(self::MOEDA);
                }

                $linha = $sheet->getStyle("A{$total}:{$ate}{$total}");
                $linha->getFont()->setBold(true);
                $linha->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
            },
        ];
    }
}
