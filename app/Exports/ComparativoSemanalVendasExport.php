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

/** Tabela do comparativo semanal: uma linha por semana (segunda a domingo), com total no fim. */
class ComparativoSemanalVendasExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithColumnFormatting
{
    private int $totalLinhas;

    public function __construct(private Collection $semanas, private object $totais, private bool $comparar = false)
    {
        $this->totalLinhas = $semanas->count();
    }

    public function headings(): array
    {
        $colunas = ['Semana', 'Documentos', 'Itens', 'Valor bruto', 'Descontos', 'Acréscimos', 'Valor líquido', 'NFC-e', 'NF-e', 'Ticket médio', 'Var. R$ s/ semana anterior', 'Var. % s/ semana anterior'];

        if ($this->comparar) {
            $colunas[] = 'Mesma semana ano anterior';
            $colunas[] = 'Var. % s/ ano anterior';
        }

        $colunas[] = '% do total';

        return $colunas;
    }

    public function array(): array
    {
        $t = $this->totais;

        $dados = $this->semanas->map(function ($s) use ($t) {
            $linha = [
                $s['rotulo'] . ($s['parcial'] ? ' (parcial)' : ''),
                (int) $s['documentos'],
                (int) $s['itens'],
                (float) $s['valor_bruto'],
                (float) $s['desconto'],
                (float) $s['acrescimo'],
                (float) $s['valor_liquido'],
                (float) $s['liquido_nfce'],
                (float) $s['liquido_nfe'],
                (float) $s['ticket_medio'],
                (float) $s['variacao_valor'],
                $s['variacao_percentual'] !== null ? (float) $s['variacao_percentual'] / 100 : null,
            ];

            if ($this->comparar) {
                $linha[] = (float) ($s['ano_anterior']['valor_liquido'] ?? 0);
                $linha[] = ($s['variacao_ano_percentual'] ?? null) !== null ? (float) $s['variacao_ano_percentual'] / 100 : null;
            }

            $linha[] = $t->valor_liquido > 0 ? (float) $s['valor_liquido'] / $t->valor_liquido : null;

            return $linha;
        })->all();

        $total = [
            'TOTAL', (int) $t->documentos, (int) $t->itens, (float) $t->valor_bruto, (float) $t->desconto,
            (float) $t->acrescimo, (float) $t->valor_liquido, (float) $t->liquido_nfce, (float) $t->liquido_nfe,
            (float) $t->ticket_medio, '', '',
        ];

        if ($this->comparar) {
            $total[] = (float) ($t->ano_anterior ?? 0);
            $total[] = $t->variacao_ano !== null ? (float) $t->variacao_ano / 100 : null;
        }

        $total[] = $t->valor_liquido > 0 ? 1.0 : 0.0;
        $dados[] = $total;

        return $dados;
    }

    public function columnFormats(): array
    {
        $dinheiro = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1;

        // A = semana, B = documentos, C = itens, D..K = valores, L = var. %, depois ano anterior e % do total
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