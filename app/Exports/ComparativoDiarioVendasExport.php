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

/** Tabela do comparativo diário: uma linha por dia, com total no fim. */
class ComparativoDiarioVendasExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithColumnFormatting
{
    private int $totalLinhas;

    public function __construct(
        private Collection $dias,
        private object $totais,
        private bool $comparar = false,
        private string $tituloComparacao = ''
    ) {
        $this->totalLinhas = $dias->count();
    }

    public function headings(): array
    {
        $colunas = ['Dia', 'Documentos', 'Itens', 'Valor bruto', 'Descontos', 'Acréscimos', 'Valor líquido', 'NFC-e', 'NF-e', 'Ticket médio', 'Var. R$ s/ dia anterior', 'Var. % s/ dia anterior'];

        if ($this->comparar) {
            $colunas[] = $this->tituloComparacao;
            $colunas[] = 'Var. % s/ comparação';
        }

        $colunas[] = '% do total';

        return $colunas;
    }

    public function array(): array
    {
        $t = $this->totais;

        $dados = $this->dias->map(function ($d) use ($t) {
            $linha = [
                $d['rotulo'] . ($d['parcial'] ? ' (parcial)' : ''),
                (int) $d['documentos'],
                (int) $d['itens'],
                (float) $d['valor_bruto'],
                (float) $d['desconto'],
                (float) $d['acrescimo'],
                (float) $d['valor_liquido'],
                (float) $d['liquido_nfce'],
                (float) $d['liquido_nfe'],
                (float) $d['ticket_medio'],
                (float) $d['variacao_valor'],
                $d['variacao_percentual'] !== null ? (float) $d['variacao_percentual'] / 100 : null,
            ];

            if ($this->comparar) {
                $linha[] = (float) ($d['comparacao']['valor_liquido'] ?? 0);
                $linha[] = ($d['variacao_comparacao_percentual'] ?? null) !== null ? (float) $d['variacao_comparacao_percentual'] / 100 : null;
            }

            $linha[] = $t->valor_liquido > 0 ? (float) $d['valor_liquido'] / $t->valor_liquido : null;

            return $linha;
        })->all();

        $total = [
            'TOTAL', (int) $t->documentos, (int) $t->itens, (float) $t->valor_bruto, (float) $t->desconto,
            (float) $t->acrescimo, (float) $t->valor_liquido, (float) $t->liquido_nfce, (float) $t->liquido_nfe,
            (float) $t->ticket_medio, '', '',
        ];

        if ($this->comparar) {
            $total[] = (float) ($t->comparacao ?? 0);
            $total[] = $t->variacao_comparacao !== null ? (float) $t->variacao_comparacao / 100 : null;
        }

        $total[] = $t->valor_liquido > 0 ? 1.0 : 0.0;
        $dados[] = $total;

        return $dados;
    }

    public function columnFormats(): array
    {
        $dinheiro = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1;

        // A = dia, B = documentos, C = itens, D..K = valores, L = var. %, depois comparação e % do total
        $formatos = array_fill_keys(range('D', 'K'), $dinheiro) + ['L' => '0.00%'];

        if ($this->comparar) {
            return $formatos + ['M' => $dinheiro, 'N' => '0.00%', 'O' => '0.00%'];
        }

        return $formatos + ['M' => '0.00%'];
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
