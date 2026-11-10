<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Comandas > Relatórios > Pedidos (linhas montadas pelo RelatorioPedidoComandaController).
 *
 * Detalhada: uma linha por ITEM (dados do pedido repetidos para filtrar/agrupar no Excel).
 *            Os valores do pedido só aparecem na 1ª linha de cada pedido, para a soma das colunas não duplicar.
 * Resumida:  uma linha por PEDIDO.
 */
class RelatorioPedidoComandaExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithColumnFormatting
{
    private const COLUNAS_PEDIDO = [
        'Origem', 'Pedido', 'Detalhe', 'Data', 'Cliente', 'Garçom / Entrega', 'Pagamento', 'Status', 'NFC-e', 'Observação',
    ];

    private const COLUNAS_ITEM = ['Item', 'Observação do item', 'Quantidade', 'Valor unit.', 'Total do item'];

    private const COLUNAS_VALOR = ['Taxa de entrega', 'Descontos', 'Total do pedido'];

    private const ORIGENS = ['mesa' => 'Mesa', 'ifood' => 'iFood'];

    private array $cabecalhos;

    public function __construct(private Collection $pedidos, private bool $detalhada)
    {
        $this->cabecalhos = $detalhada
            ? array_merge(self::COLUNAS_PEDIDO, self::COLUNAS_ITEM, self::COLUNAS_VALOR)
            : array_merge(self::COLUNAS_PEDIDO, ['Qtd. de itens'], self::COLUNAS_VALOR);
    }

    public function headings(): array
    {
        return $this->cabecalhos;
    }

    public function collection(): Collection
    {
        $linhas = collect();

        foreach ($this->pedidos as $pedido) {
            $dados = $this->dadosPedido($pedido);
            $valores = [(float) $pedido['taxa_entrega'], (float) $pedido['descontos'], (float) $pedido['total']];

            if (! $this->detalhada) {
                $linhas->push(array_merge($dados, [array_sum(array_column($pedido['itens'], 'quantidade'))], $valores));
                continue;
            }

            if (! $pedido['itens']) {
                $linhas->push(array_merge($dados, array_fill(0, count(self::COLUNAS_ITEM), ''), $valores));
                continue;
            }

            foreach (array_values($pedido['itens']) as $i => $item) {
                $linhas->push(array_merge($dados, [
                    $item['nome'],
                    $item['observacao'],
                    (int) $item['quantidade'],
                    (float) $item['preco_unitario'],
                    (float) $item['subtotal'],
                ], $i === 0 ? $valores : [null, null, null]));
            }
        }

        return $linhas;
    }

    private function dadosPedido(array $pedido): array
    {
        return [
            self::ORIGENS[$pedido['origem']] ?? $pedido['origem'],
            $pedido['pedido'],
            $pedido['detalhe'],
            $pedido['momento']?->format('d/m/Y H:i') ?? '',
            $pedido['cliente'],
            $pedido['atendimento'],
            $pedido['pagamento'],
            $pedido['status_rotulo'],
            $pedido['nfce'],
            $pedido['observacao'],
        ];
    }

    private function letra(string $cabecalho): ?string
    {
        $posicao = array_search($cabecalho, $this->cabecalhos, true);

        return $posicao === false ? null : Coordinate::stringFromColumnIndex($posicao + 1);
    }

    public function columnFormats(): array
    {
        $formatos = [];

        foreach (['Valor unit.', 'Total do item', 'Taxa de entrega', 'Descontos', 'Total do pedido'] as $coluna) {
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
