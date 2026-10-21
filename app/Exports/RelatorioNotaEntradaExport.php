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
 * Detalhada: uma linha por ITEM (dados da nota repetidos para filtrar/agrupar no Excel).
 *            Os totais da nota só aparecem na 1ª linha de cada nota, para a soma da coluna não duplicar.
 * Resumida:  uma linha por NOTA.
 */
class RelatorioNotaEntradaExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    ShouldAutoSize,
    WithStyles,
    WithColumnFormatting,
    WithCustomValueBinder
{
    private const COLUNAS_NOTA = [
        'Série', 'Número', 'Entrada', 'Emissão', 'Fornecedor', 'CPF/CNPJ', 'Operação',
        'Origem', 'Status', 'Chave de acesso',
    ];

    private const COLUNAS_ITEM = [
        'Código', 'Cód. fornecedor', 'Descrição', 'Variação', 'Und', 'Quantidade', 'Un./caixa',
        'Qtd. estoque', 'Valor unit.', 'Desconto item', 'Total item', 'Lote', 'Validade',
    ];

    private const COLUNAS_TOTAIS = [
        'Produtos', 'Frete', 'Outras despesas', 'Desconto', 'Total da nota', 'Total do XML',
        'Finalizada em', 'Usuário',
    ];

    /** Colunas que precisam ficar como TEXTO (zeros à esquerda / números longos). */
    private const TEXTO = ['Chave de acesso', 'Código', 'Cód. fornecedor', 'CPF/CNPJ', 'Lote'];

    private array $cabecalhos;

    private ?array $letrasTexto = null;

    public function __construct(private Collection $entradas, private bool $detalhada)
    {
        $this->cabecalhos = $detalhada
            ? array_merge(self::COLUNAS_NOTA, self::COLUNAS_ITEM, self::COLUNAS_TOTAIS)
            : array_merge(self::COLUNAS_NOTA, self::COLUNAS_TOTAIS);
    }

    public function headings(): array
    {
        return $this->cabecalhos;
    }

    public function collection(): Collection
    {
        $linhas = collect();

        foreach ($this->entradas as $entrada) {
            $dadosNota = $this->dadosNota($entrada);
            $totais = $this->totaisNota($entrada);

            if (! $this->detalhada) {
                $linhas->push(array_merge($dadosNota, $totais));
                continue;
            }

            if ($entrada->itens->isEmpty()) {
                $linhas->push(array_merge($dadosNota, array_fill(0, count(self::COLUNAS_ITEM), ''), $totais));
                continue;
            }

            $semTotais = array_map(fn () => null, $totais);

            foreach ($entrada->itens->values() as $i => $item) {
                $linhas->push(array_merge(
                    $dadosNota,
                    $this->dadosItem($item),
                    $i === 0 ? $totais : $semTotais
                ));
            }
        }

        return $linhas;
    }

    private function dadosNota($entrada): array
    {
        $fornecedor = $entrada->fornecedor;

        return [
            (string) $entrada->serie,
            (int) $entrada->numero,
            $entrada->data_entrada?->format('d/m/Y') ?? '',
            $entrada->data_emissao?->format('d/m/Y') ?? '',
            $fornecedor->nome ?? '',
            self::formatarDocumento($fornecedor->cpf_cnpj ?? null),
            $entrada->operacao->descricao ?? '',
            $entrada->tipo_entrada === 'xml' ? 'XML' : 'Digitada',
            $entrada->status === 'finalizada' ? 'Finalizada' : ($entrada->status === 'rascunho' ? 'Rascunho' : ucfirst((string) $entrada->status)),
            (string) $entrada->chave_acesso,
        ];
    }

    private function dadosItem($item): array
    {
        $porCaixa = max((int) $item->qtd_por_caixa, 1);

        return [
            (string) ($item->produto->codigo_interno ?? ''),
            (string) $item->codigo_fornecedor,
            (string) ($item->descricao ?: ($item->produto->nome ?? '')),
            $item->variante?->rotulo() ?: '',
            (string) ($item->unidade ?: ($item->produto->unidade_comercial ?? '')),
            (float) $item->quantidade,
            $porCaixa,
            (float) $item->quantidade_estoque,
            (float) $item->valor_unitario,
            (float) $item->valor_desconto,
            (float) $item->valor_total,
            (string) $item->lote,
            $item->validade?->format('d/m/Y') ?? '',
        ];
    }

    private function totaisNota($entrada): array
    {
        return [
            (float) $entrada->valor_produtos,
            (float) $entrada->valor_frete,
            (float) $entrada->valor_outras,
            (float) $entrada->valor_desconto,
            (float) $entrada->valor_total,
            $entrada->valor_total_xml !== null ? (float) $entrada->valor_total_xml : null,
            $entrada->finalizada_em?->format('d/m/Y H:i') ?? '',
            $entrada->usuario->name ?? '',
        ];
    }

    private static function formatarDocumento(?string $valor): string
    {
        $d = preg_replace('/\D/', '', (string) $valor);

        return match (strlen($d)) {
            11 => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d),
            14 => preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d),
            default => (string) $valor,
        };
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

        foreach (['Desconto item', 'Total item', 'Produtos', 'Frete', 'Outras despesas', 'Desconto', 'Total da nota', 'Total do XML'] as $coluna) {
            if ($letra = $this->letra($coluna)) {
                $formatos[$letra] = '"R$" #,##0.00';
            }
        }

        // Valor unitário pode ter até 4 casas
        if ($letra = $this->letra('Valor unit.')) {
            $formatos[$letra] = '"R$" #,##0.00##';
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
