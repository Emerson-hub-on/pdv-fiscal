<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Detalhada: uma linha por ITEM devolvido (dados da troca repetidos para filtrar/agrupar no Excel).
 *            Os valores da troca só aparecem na 1ª linha de cada troca, para a soma da coluna não duplicar.
 * Resumida:  uma linha por TROCA.
 */
class RelatorioTrocaExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    ShouldAutoSize,
    WithStyles,
    WithColumnFormatting,
    WithCustomValueBinder
{
    private const COLUNAS_TROCA = [
        'Data da troca', 'NF-e de entrada', 'Situação', 'CFOP', 'Cliente', 'CPF/CNPJ',
        'Venda original', 'Nº da venda', 'Data da venda', 'Chave da venda',
    ];

    private const COLUNAS_ITEM = ['Nº do item na venda', 'Produto', 'Quantidade devolvida', 'Unidade', 'Valor do item'];

    private const COLUNAS_VALORES = ['Valor devolvido', 'Vale-troca', 'Crédito gerado', 'Crédito usado', 'Saldo do crédito', 'Saída do item novo'];

    /** Colunas que precisam ficar como TEXTO. */
    private const TEXTO = ['CPF/CNPJ', 'Chave da venda', 'Vale-troca'];

    private const MOEDA = ['Valor do item', 'Valor devolvido', 'Crédito gerado', 'Crédito usado', 'Saldo do crédito'];

    private array $cabecalhos;

    private ?array $letrasTexto = null;

    public function __construct(private Collection $trocas, private bool $detalhada)
    {
        $this->cabecalhos = $detalhada
            ? array_merge(self::COLUNAS_TROCA, self::COLUNAS_ITEM, self::COLUNAS_VALORES)
            : array_merge(self::COLUNAS_TROCA, self::COLUNAS_VALORES);
    }

    public function headings(): array
    {
        return $this->cabecalhos;
    }

    public function collection(): Collection
    {
        $linhas = collect();

        foreach ($this->trocas as $troca) {
            $dados = $this->dadosTroca($troca);
            $valores = $this->valores($troca);

            if (! $this->detalhada) {
                $linhas->push(array_merge($dados, $valores));
                continue;
            }

            if ($troca->itens->isEmpty()) {
                $linhas->push(array_merge($dados, array_fill(0, count(self::COLUNAS_ITEM), ''), $valores));
                continue;
            }

            $semValores = array_map(fn () => null, $valores);

            foreach ($troca->itens->values() as $i => $item) {
                $linhas->push(array_merge($dados, [
                    $item->ref_nitem ? (int) $item->ref_nitem : '',
                    $item->descricao ?: ($item->produto->nome ?? ''),
                    (float) $item->quantidade,
                    $item->produto->unidade_comercial ?? '',
                    (float) $item->valor_total,
                ], $i === 0 ? $valores : $semValores));
            }
        }

        return $linhas;
    }

    private function dadosTroca($troca): array
    {
        $o = $troca->rel_original;

        return [
            ($troca->emitida_em ?? $troca->created_at)?->format('d/m/Y H:i') ?? '',
            $troca->numero ? "nº {$troca->numero} / série {$troca->serie}" : "Rascunho #{$troca->id}",
            match ($troca->status) { 'emitida' => 'Autorizada', 'cancelada' => 'Cancelada', default => 'Rascunho' },
            (string) ($troca->cfopSaida->codigo ?? ''),
            $troca->cliente->nome ?? '',
            (string) ($troca->cliente->cpf_cnpj_formatado ?? ''),
            $o['tipo'],
            $o['numero'] ? "{$o['numero']} / série {$o['serie']}" : '',
            $o['data'] ? \Carbon\Carbon::parse($o['data'])->format('d/m/Y') : '',
            (string) ($o['chave'] ?? ''),
        ];
    }

    private function valores($troca): array
    {
        $credito = $troca->rel_credito;

        return [
            (float) $troca->valor_total,
            $credito->codigo ?? '',
            $credito ? (float) $credito->valor : null,
            $credito ? (float) $troca->rel_credito_usado : null,
            $credito ? (float) $credito->saldo : null,
            $troca->rel_saidas->implode(', '),
        ];
    }

    /** Letra da coluna de um cabeçalho (A, B, ...), ou null se não existir. */
    private function letra(string $cabecalho): ?string
    {
        $posicao = array_search($cabecalho, $this->cabecalhos, true);

        return $posicao === false ? null : Coordinate::stringFromColumnIndex($posicao + 1);
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value) && $value !== '') {
            $this->letrasTexto ??= array_values(array_filter(array_map(fn ($c) => $this->letra($c), self::TEXTO)));

            if (in_array($cell->getColumn(), $this->letrasTexto, true)) {
                $cell->setValueExplicit($value, DataType::TYPE_STRING);

                return true;
            }
        }

        return parent::bindValue($cell, $value);
    }

    public function columnFormats(): array
    {
        $formatos = [];

        foreach (self::MOEDA as $coluna) {
            if ($letra = $this->letra($coluna)) {
                $formatos[$letra] = '"R$" #,##0.00';
            }
        }

        return $formatos;
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
