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
 * Visão "resumida": uma linha por modalidade (dinheiro, crédito, débito, PIX, boleto, outros).
 * Visão "detalhada": uma linha por pagamento (cada NFC-e ou NF-e com a sua forma de pagamento).
 */
class VendasFormaPagamentoExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithColumnFormatting
{
    private int $totalLinhas;

    public function __construct(private Collection $linhas, private object $resumo, private string $visao = 'resumida')
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
            ? ['Data', 'Tipo', 'Nº documento', 'Série', 'Forma de pagamento', 'Modalidade', 'Prazo', 'Valor']
            : ['Modalidade', 'Pagamentos', 'NFC-e', 'NF-e', 'À vista', 'A prazo', 'Total', '% do total', 'Valor médio'];
    }

    public function array(): array
    {
        $r = $this->resumo;

        if ($this->detalhada()) {
            $rotulos = array_map(fn ($m) => $m->rotulo, $r->modalidades);

            $dados = $this->linhas->map(fn ($l) => [
                $l->data ? date('d/m/Y H:i', strtotime($l->data)) : '',
                $l->tipo,
                $l->numero,
                $l->serie,
                $l->forma,
                $rotulos[$l->modalidade] ?? $l->modalidade,
                $l->a_prazo ? 'A prazo' : 'À vista',
                (float) $l->valor,
            ])->all();

            $dados[] = ['TOTAL', '', '', '', '', '', '', (float) $this->linhas->sum('valor')];

            return $dados;
        }

        $dados = $this->linhas->map(fn ($m) => [
            $m->rotulo,
            (int) $m->pagamentos,
            (float) $m->nfce,
            (float) $m->nfe,
            (float) $m->a_vista,
            (float) $m->a_prazo,
            (float) $m->total,
            (float) $m->percentual / 100,
            (float) $m->ticket_medio,
        ])->all();

        $dados[] = [
            'TOTAL', (int) $r->pagamentos, (float) $r->nfce, (float) $r->nfe,
            (float) $r->a_vista, (float) $r->a_prazo, (float) $r->total, $r->total > 0 ? 1.0 : 0.0,
            $r->pagamentos > 0 ? (float) $r->total / $r->pagamentos : 0.0,
        ];

        return $dados;
    }

    public function columnFormats(): array
    {
        $dinheiro = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1;

        return $this->detalhada()
            ? ['H' => $dinheiro]
            : ['C' => $dinheiro, 'D' => $dinheiro, 'E' => $dinheiro, 'F' => $dinheiro, 'G' => $dinheiro, 'H' => '0.00%', 'I' => $dinheiro];
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
