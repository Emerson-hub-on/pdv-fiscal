<?php

namespace App\Exports;

use App\Models\MovimentacaoEstoque;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SaidaProdutoExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    WithMapping,
    ShouldAutoSize,
    WithStyles,
    WithColumnFormatting,
    WithCustomValueBinder
{
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
            'Data/hora', 'Tipo', 'Código', 'Cód. Barras', 'Descrição', 'Variação', 'Und',
            'Quantidade', 'Saldo anterior', 'Saldo posterior', 'Custo unit.', 'Custo total',
            'Documento', 'Operador', 'Observação',
        ];
    }

    public function map($item): array
    {
        $quantidade = (float) $item->quantidade;
        $custo = $item->custo_unitario !== null ? (float) $item->custo_unitario : null;

        return [
            Carbon::parse($item->created_at)->format('d/m/Y H:i'),
            MovimentacaoEstoque::TIPOS[$item->tipo] ?? $item->tipo,
            (string) $item->codigo_interno,
            (string) ($item->codigo_barras ?: ''),
            $item->descricao,
            $item->variacao ?: '',
            $item->unidade,
            $quantidade,
            (float) $item->saldo_anterior,
            (float) $item->saldo_posterior,
            $custo,
            $custo !== null ? abs($quantidade) * $custo : null,
            $item->documento_rotulo ?: '',
            $item->operador ?: '',
            $item->observacao ?: '',
        ];
    }

    /** Código e código de barras como TEXTO (senão o Excel come zeros à esquerda). */
    public function bindValue(Cell $cell, $value): bool
    {
        if (in_array($cell->getColumn(), ['C', 'D'], true) && is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function columnFormats(): array
    {
        return [
            'K' => '"R$" #,##0.00',
            'L' => '"R$" #,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
