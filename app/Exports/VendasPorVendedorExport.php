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
 * Visão "resumida": uma linha por vendedor. Visão "detalhada": uma linha por venda (NFC-e ou NF-e).
 */
class VendasPorVendedorExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithColumnFormatting
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
            ? ['Data', 'Tipo', 'Nº documento', 'Série', 'Cód. vendedor', 'Vendedor', 'Itens', 'Qtd', 'Valor bruto', 'Descontos', 'Acréscimos', 'Valor líquido']
            : ['Cód.', 'Vendedor', 'Perfil', 'Situação', 'Documentos', 'Itens', 'Qtd', 'Valor bruto', 'Descontos', 'Acréscimos', 'Valor líquido', 'Ticket médio', '% do total'];
    }

    public function array(): array
    {
        $t = $this->totais;

        if ($this->detalhada()) {
            $dados = $this->linhas->map(fn ($l) => [
                $l->data ? date('d/m/Y H:i', strtotime($l->data)) : '',
                $l->tipo,
                $l->numero,
                $l->serie,
                $l->codigo_vendedor,
                $l->vendedor,
                (int) $l->itens,
                (float) $l->quantidade,
                (float) $l->valor_bruto,
                (float) $l->desconto,
                (float) $l->acrescimo,
                (float) $l->valor_liquido,
            ])->all();

            $dados[] = [
                'TOTAL', '', '', '', '', '', (int) $this->linhas->sum('itens'), (float) $this->linhas->sum('quantidade'),
                (float) $this->linhas->sum('valor_bruto'), (float) $this->linhas->sum('desconto'),
                (float) $this->linhas->sum('acrescimo'), (float) $this->linhas->sum('valor_liquido'),
            ];

            return $dados;
        }

        $dados = $this->linhas->map(function ($l) use ($t) {
            $perfil = collect([$l->is_admin ? 'Administrador' : null, $l->perfil])->filter()->implode(', ');

            return [
                $l->codigo_vendedor,
                $l->vendedor,
                $perfil !== '' ? $perfil : '—',
                $l->operador_id === null ? '' : ($l->vendedor_ativo ? 'Ativo' : 'Inativo'),
                (int) $l->documentos,
                (int) $l->itens,
                (float) $l->quantidade,
                (float) $l->valor_bruto,
                (float) $l->desconto,
                (float) $l->acrescimo,
                (float) $l->valor_liquido,
                (float) $l->ticket_medio,
                $t->valor_liquido > 0 ? (float) $l->valor_liquido / (float) $t->valor_liquido : null,
            ];
        })->all();

        $dados[] = [
            'TOTAL', '', '', '', (int) $t->documentos, (int) $t->itens, (float) $t->quantidade,
            (float) $t->valor_bruto, (float) $t->desconto, (float) $t->acrescimo, (float) $t->valor_liquido,
            (float) $t->ticket_medio, $t->valor_liquido > 0 ? 1.0 : 0.0,
        ];

        return $dados;
    }

    public function columnFormats(): array
    {
        $dinheiro = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1;

        // Detalhada: Qtd = H, valores I..L. Resumida: Qtd = G, valores H..L, % = M.
        return $this->detalhada()
            ? ['H' => '#,##0.###'] + array_fill_keys(range('I', 'L'), $dinheiro)
            : ['G' => '#,##0.###'] + array_fill_keys(range('H', 'L'), $dinheiro) + ['M' => '0.00%'];
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
