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

/** Tabela do comparativo mensal: uma linha por mês, com total no fim. */
class ComparativoMensalVendasExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithColumnFormatting
{
    private int $totalLinhas;

    public function __construct(private Collection $meses, private object $totais, private bool $comparar = false)
    {
        $this->totalLinhas = $meses->count();
    }

    public function headings(): array
    {
        $colunas = ['Mês', 'Documentos', 'Itens', 'Valor bruto', 'Descontos', 'Acréscimos', 'Valor líquido', 'NFC-e', 'NF-e', 'Ticket médio', 'Var. R$ s/ mês anterior', 'Var. % s/ mês anterior'];

        if ($this->comparar) {
            $colunas[] = 'Mesmo mês ano anterior';
            $colunas[] = 'Var. % s/ ano anterior';
        }

        $colunas[] = '% do total';

        return $colunas;
    }

    public function array(): array
    {
        $t = $this->totais;

        $dados = $this->meses->map(function ($m) use ($t) {
            $linha = [
                $m['rotulo'] . ($m['parcial'] ? ' (parcial)' : ''),
                (int) $m['documentos'],
                (int) $m['itens'],
                (float) $m['valor_bruto'],
                (float) $m['desconto'],
                (float) $m['acrescimo'],
                (float) $m['valor_liquido'],
                (float) $m['liquido_nfce'],
                (float) $m['liquido_nfe'],
                (float) $m['ticket_medio'],
                (float) $m['variacao_valor'],
                $m['variacao_percentual'] !== null ? (float) $m['variacao_percentual'] / 100 : null,
            ];

            if ($this->comparar) {
                $linha[] = (float) ($m['ano_anterior']['valor_liquido'] ?? 0);
                $linha[] = ($m['variacao_ano_percentual'] ?? null) !== null ? (float) $m['variacao_ano_percentual'] / 100 : null;
            }

            $linha[] = $t->valor_liquido > 0 ? (float) $m['valor_liquido'] / $t->valor_liquido : null;

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

        $formatos = [
            'D' => $dinheiro, 'E' => $dinheiro, 'F' => $dinheiro, 'G' => $dinheiro, 'H' => $dinheiro,
            'I' => $dinheiro, 'J' => $dinheiro, 'K' => $dinheiro, 'L' => '0.00%',
        ];

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
