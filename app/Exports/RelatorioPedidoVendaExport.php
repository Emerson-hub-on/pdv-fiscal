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
 * Detalhada: uma linha por ITEM (dados do pedido repetidos para filtrar/agrupar no Excel).
 *            O total do pedido só aparece na 1ª linha de cada pedido, para a soma da coluna não duplicar.
 * Resumida:  uma linha por PEDIDO.
 */
class RelatorioPedidoVendaExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    ShouldAutoSize,
    WithStyles,
    WithColumnFormatting,
    WithCustomValueBinder
{
    private const COLUNAS_PEDIDO = [
        'Pedido', 'Data do pedido', 'Previsão de entrega', 'Cliente', 'CPF/CNPJ', 'Operador',
        'Forma de pagamento', 'Status', 'Observação',
    ];

    private const COLUNAS_ITEM = [
        'Código', 'Cód. barras', 'Descrição', 'Und', 'Quantidade', 'Valor unit.', 'Total do item',
    ];

    private const STATUS = ['finalizado' => 'Finalizado', 'emitido' => 'Emitido', 'cancelado' => 'Cancelado'];

    /** Colunas que precisam ficar como TEXTO (zeros à esquerda / números longos). */
    private const TEXTO = ['Código', 'Cód. barras', 'CPF/CNPJ'];

    private array $cabecalhos;

    private ?array $letrasTexto = null;

    public function __construct(private Collection $pedidos, private bool $detalhada)
    {
        $this->cabecalhos = $detalhada
            ? array_merge(self::COLUNAS_PEDIDO, self::COLUNAS_ITEM, ['Total do pedido'])
            : array_merge(self::COLUNAS_PEDIDO, ['Qtd. de itens', 'Total do pedido']);
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
            $total = (float) $pedido->valor_total;

            if (! $this->detalhada) {
                $linhas->push(array_merge($dadosPedido, [$pedido->itens->count(), $total]));
                continue;
            }

            if ($pedido->itens->isEmpty()) {
                $linhas->push(array_merge($dadosPedido, array_fill(0, count(self::COLUNAS_ITEM), ''), [$total]));
                continue;
            }

            foreach ($pedido->itens->values() as $i => $item) {
                $linhas->push(array_merge($dadosPedido, $this->dadosItem($item), [$i === 0 ? $total : null]));
            }
        }

        return $linhas;
    }

    private function dadosPedido($pedido): array
    {
        $cliente = $pedido->cliente;
        $nome = $cliente ? ($cliente->nome_exibicao ?? $cliente->nome ?? $cliente->razao_social ?? '') : '';

        return [
            (int) $pedido->numero,
            $pedido->data_pedido?->format('d/m/Y') ?? '',
            $pedido->previsao_entrega?->format('d/m/Y') ?? '',
            (string) $nome,
            (string) ($cliente->cpf_cnpj_formatado ?? $cliente->cpf_cnpj ?? ''),
            $pedido->operador->name ?? '',
            $pedido->formaPagamento->descricao ?? '',
            self::STATUS[$pedido->status] ?? ucfirst((string) $pedido->status),
            (string) $pedido->descricao,
        ];
    }

    private function dadosItem($item): array
    {
        return [
            (string) ($item->produto->codigo_interno ?? ''),
            (string) $item->codigo_barras,
            (string) $item->descricao,
            (string) $item->unidade,
            (float) $item->quantidade,
            (float) $item->valor_unitario,
            $item->total(),
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

        foreach (['Valor unit.', 'Total do item', 'Total do pedido'] as $coluna) {
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