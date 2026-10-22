<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Detalhada: uma linha por ITEM (dados do pedido repetidos para filtrar/agrupar no Excel).
 * Resumida:  uma linha por PEDIDO.
 */
class RelatorioPedidoCompraExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    ShouldAutoSize,
    WithStyles,
    WithCustomValueBinder
{
    private const COLUNAS_PEDIDO = [
        'Pedido', 'Data do pedido', 'Previsão de entrega', 'Dias de atraso', 'Fornecedor', 'CPF/CNPJ', 'Status', 'Observação',
    ];

    private const COLUNAS_ITEM = [
        'Código', 'Cód. barras', 'Descrição', 'Und', 'Quantidade', 'Estoque atual',
    ];

    /** Colunas que precisam ficar como TEXTO (zeros à esquerda / números longos). */
    private const TEXTO = ['Código', 'Cód. barras', 'CPF/CNPJ'];

    private const STATUS = ['aberto' => 'Aberto', 'enviado' => 'Enviado', 'cancelado' => 'Cancelado'];

    private array $cabecalhos;

    private ?array $letrasTexto = null;

    public function __construct(private Collection $pedidos, private bool $detalhada)
    {
        $this->cabecalhos = $detalhada
            ? array_merge(self::COLUNAS_PEDIDO, self::COLUNAS_ITEM)
            : array_merge(self::COLUNAS_PEDIDO, ['Qtd. de itens']);
    }

    public function headings(): array
    {
        return $this->cabecalhos;
    }

    public function collection(): Collection
    {
        $linhas = collect();

        foreach ($this->pedidos as $pedido) {
            $dadosPedido = $this->dadosPedido($pedido);

            if (! $this->detalhada) {
                $linhas->push(array_merge($dadosPedido, [$pedido->itens->count()]));
                continue;
            }

            if ($pedido->itens->isEmpty()) {
                $linhas->push(array_merge($dadosPedido, array_fill(0, count(self::COLUNAS_ITEM), '')));
                continue;
            }

            foreach ($pedido->itens as $item) {
                $linhas->push(array_merge($dadosPedido, $this->dadosItem($item)));
            }
        }

        return $linhas;
    }

    /** Pedido aberto ou enviado com a previsão de entrega já vencida: dias de atraso. */
    public static function diasAtraso($pedido): int
    {
        if (! in_array($pedido->status, ['aberto', 'enviado'], true) || ! $pedido->previsao_entrega) {
            return 0;
        }

        return max((int) $pedido->previsao_entrega->diffInDays(today(), false), 0);
    }

    private function dadosPedido($pedido): array
    {
        return [
            (int) $pedido->numero,
            $pedido->data_pedido?->format('d/m/Y') ?? '',
            $pedido->previsao_entrega?->format('d/m/Y') ?? '',
            self::diasAtraso($pedido) ?: '',
            $pedido->fornecedor->nome_exibicao ?? '',
            $pedido->fornecedor->documento_formatado ?? '',
            self::STATUS[$pedido->status] ?? ucfirst((string) $pedido->status),
            (string) $pedido->observacao,
        ];
    }

    private function dadosItem($item): array
    {
        // o estoque mora na variante quando o item é de uma variação
        $estoque = $item->variante?->estoque ?? $item->produto?->estoque;

        return [
            (string) ($item->produto->codigo_interno ?? ''),
            (string) $item->codigo_barras,
            (string) $item->descricao,
            (string) $item->unidade,
            (float) $item->quantidade,
            $estoque !== null ? (float) $estoque : '',
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

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
