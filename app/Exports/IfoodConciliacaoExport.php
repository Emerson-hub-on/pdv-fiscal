<?php

namespace App\Exports;

use App\Services\IfoodFinanceiroService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Conciliação iFood: uma linha por pedido, com os valores por categoria, o líquido e a situação. */
class IfoodConciliacaoExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithColumnFormatting
{
    private const SITUACOES = [
        'ok' => 'Conferido', 'divergente' => 'Divergente', 'sem_evento' => 'Sem lançamento do iFood',
        'sem_sistema' => 'Fora do sistema', 'aguardando' => 'Aguardando o iFood',
    ];

    public function __construct(private Collection $linhas)
    {
    }

    public function headings(): array
    {
        return array_merge(
            ['Pedido', 'Pedido no iFood (id)', 'Data', 'Tipo', 'Cliente', 'Total no sistema'],
            array_values(IfoodFinanceiroService::CATEGORIAS),
            ['Líquido do repasse', 'Recebido direto', 'Previsão do repasse', 'Situação', 'Observação']
        );
    }

    public function collection(): Collection
    {
        return $this->linhas->map(fn (array $l) => array_merge(
            [
                $l['pedido'], $l['order_id'],
                $l['data'] ? \Carbon\Carbon::parse($l['data'])->format('d/m/Y H:i') : '',
                $l['tipo'], $l['cliente'], $l['sistema'],
            ],
            array_values($l['categorias']),
            [
                $l['liquido'], $l['direto'],
                $l['previsao'] ? \Carbon\Carbon::parse($l['previsao'])->format('d/m/Y') : '',
                self::SITUACOES[$l['situacao']] ?? $l['situacao'], (string) $l['motivo'],
            ]
        ));
    }

    public function columnFormats(): array
    {
        // Colunas de valor: F (sistema) até a coluna do "Recebido direto"
        $formatos = [];
        $ultima = 6 + count(IfoodFinanceiroService::CATEGORIAS) + 2;
        for ($i = 6; $i <= $ultima; $i++) {
            $formatos[\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i)] = '"R$" #,##0.00';
        }

        return $formatos;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

        return [1 => ['font' => ['bold' => true]]];
    }
}
