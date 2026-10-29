<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Planilha para a contagem física.
 *
 * - Colunas A e B (ids) identificam o item na importação: não podem ser alteradas.
 * - Custo unitário, valor no sistema, valor contado e diferença são só para conferência
 *   (fórmulas do Excel): a importação usa apenas a coluna "Quantidade contada" e o custo do cadastro.
 * - Na contagem "cega" (sem saldo) saem só custo, quantidade contada e valor contado.
 */
class ContagemEstoqueExport implements FromArray, WithHeadings, ShouldAutoSize, WithColumnFormatting
{
    private array $colunas;

    public function __construct(private array $linhas, private string $rotuloSaldo, private bool $comSaldo)
    {
        $nomes = ['produto_id', 'variante_id', 'codigo', 'codigo_barras', 'descricao', 'unidade', 'custo'];
        if ($this->comSaldo) {
            array_push($nomes, 'saldo', 'valor_sistema');
        }
        array_push($nomes, 'contada', 'valor_contado');
        if ($this->comSaldo) {
            $nomes[] = 'diferenca';
        }

        // nome => letra da coluna (A, B, C...)
        foreach ($nomes as $i => $nome) {
            $this->colunas[$nome] = Coordinate::stringFromColumnIndex($i + 1);
        }
    }

    public function headings(): array
    {
        $titulos = [
            'produto_id'    => 'ID produto (não alterar)',
            'variante_id'   => 'ID variação (não alterar)',
            'codigo'        => 'Código',
            'codigo_barras' => 'Código de barras',
            'descricao'     => 'Descrição',
            'unidade'       => 'Unidade',
            'custo'         => 'Custo unitário (R$)',
            'saldo'         => $this->rotuloSaldo,
            'valor_sistema' => 'Valor no sistema (R$)',
            'contada'       => 'Quantidade contada',
            'valor_contado' => 'Valor contado (R$)',
            'diferenca'     => 'Diferença (R$)',
        ];

        return array_map(fn ($nome) => $titulos[$nome], array_keys($this->colunas));
    }

    public function array(): array
    {
        $c = $this->colunas;
        $saida = [];

        foreach (array_values($this->linhas) as $i => $l) {
            $r = $i + 2; // linha 1 = cabeçalho

            $linha = [
                'produto_id'    => $l['produto_id'],
                'variante_id'   => $l['variante_id'] ?? '',
                'codigo'        => (string) $l['codigo'],
                'codigo_barras' => (string) $l['codigo_barras'],
                'descricao'     => $l['descricao'],
                'unidade'       => $l['unidade'],
                'custo'         => $l['custo'],
                'saldo'         => $l['saldo'],
                'valor_sistema' => isset($c['saldo']) ? "={$c['saldo']}{$r}*{$c['custo']}{$r}" : '',
                'contada'       => '', // preenchida por quem contou
                // Em branco enquanto não contar, para o total mostrar só o que já foi contado
                'valor_contado' => "=IF({$c['contada']}{$r}=\"\",\"\",{$c['contada']}{$r}*{$c['custo']}{$r})",
                'diferenca'     => isset($c['saldo'])
                    ? "=IF({$c['contada']}{$r}=\"\",\"\",{$c['valor_contado']}{$r}-{$c['valor_sistema']}{$r})"
                    : '',
            ];

            $saida[] = array_map(fn ($nome) => $linha[$nome], array_keys($c));
        }

        // Linha de totais (a importação ignora: a coluna da contagem fica vazia)
        $ultima = count($saida) + 1;
        if ($ultima > 1) {
            $total = array_fill_keys(array_keys($c), '');
            $total['descricao'] = 'TOTAL';
            foreach (['valor_sistema', 'valor_contado', 'diferenca'] as $nome) {
                if (isset($c[$nome])) {
                    $total[$nome] = "=SUM({$c[$nome]}2:{$c[$nome]}{$ultima})";
                }
            }
            $saida[] = array_values($total);
        }

        return $saida;
    }

    public function columnFormats(): array
    {
        $moeda = '#,##0.00';
        $formatos = [
            // EAN longo: sem isto o Excel mostra em notação científica (7,89E+12)
            $this->colunas['codigo_barras'] => '0',
            $this->colunas['custo']         => '#,##0.0000',
            $this->colunas['contada']       => '#,##0.###',
            $this->colunas['valor_contado'] => $moeda,
        ];

        foreach (['valor_sistema', 'diferenca'] as $nome) {
            if (isset($this->colunas[$nome])) {
                $formatos[$this->colunas[$nome]] = $moeda;
            }
        }
        if (isset($this->colunas['saldo'])) {
            $formatos[$this->colunas['saldo']] = '#,##0.###';
        }

        return $formatos;
    }
}