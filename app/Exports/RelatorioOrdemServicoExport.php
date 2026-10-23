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
 * Detalhada: uma linha por PEÇA (dados da OS repetidos para filtrar/agrupar no Excel).
 *            Os valores da OS (peças, mão de obra, desconto, total) só aparecem na 1ª linha
 *            de cada OS, para a soma da coluna não duplicar.
 * Resumida:  uma linha por OS.
 */
class RelatorioOrdemServicoExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    ShouldAutoSize,
    WithStyles,
    WithColumnFormatting,
    WithCustomValueBinder
{
    private const COLUNAS_OS = [
        'OS', 'Abertura', 'Previsão de entrega', 'Dias de atraso', 'Cliente', 'Telefone', 'CPF/CNPJ',
        'Equipamento', 'Marca', 'Modelo', 'Nº de série', 'Técnico', 'Prioridade', 'Status',
        'Concluída em', 'Entregue em', 'Garantia (dias)', 'Defeito relatado', 'Laudo técnico', 'Serviço executado',
    ];

    private const COLUNAS_PECA = [
        'Cód. barras', 'Peça', 'Und', 'Quantidade', 'Valor unit.', 'Total da peça',
    ];

    private const COLUNAS_VALORES = ['Peças', 'Mão de obra', 'Desconto', 'Total da OS'];

    /** Colunas que precisam ficar como TEXTO (zeros à esquerda / números longos). */
    private const TEXTO = ['Cód. barras', 'Nº de série', 'CPF/CNPJ', 'Telefone'];

    private array $cabecalhos;

    private ?array $letrasTexto = null;

    public function __construct(private Collection $ordens, private bool $detalhada)
    {
        $this->cabecalhos = $detalhada
            ? array_merge(self::COLUNAS_OS, self::COLUNAS_PECA, self::COLUNAS_VALORES)
            : array_merge(self::COLUNAS_OS, ['Qtd. de peças'], self::COLUNAS_VALORES);
    }

    public function headings(): array
    {
        return $this->cabecalhos;
    }

    public function collection(): Collection
    {
        $linhas = collect();

        foreach ($this->ordens as $ordem) {
            $dadosOs = $this->dadosOs($ordem);
            $valores = $this->valoresOs($ordem);

            if (! $this->detalhada) {
                $linhas->push(array_merge($dadosOs, [$ordem->itens->count()], $valores));
                continue;
            }

            if ($ordem->itens->isEmpty()) {
                $linhas->push(array_merge($dadosOs, array_fill(0, count(self::COLUNAS_PECA), ''), $valores));
                continue;
            }

            $semValores = array_map(fn () => null, $valores);

            foreach ($ordem->itens->values() as $i => $item) {
                $linhas->push(array_merge($dadosOs, $this->dadosPeca($item), $i === 0 ? $valores : $semValores));
            }
        }

        return $linhas;
    }

    private function dadosOs($ordem): array
    {
        return [
            (int) $ordem->numero,
            $ordem->data_abertura?->format('d/m/Y') ?? '',
            $ordem->previsao_entrega?->format('d/m/Y') ?? '',
            $ordem->atrasada() ? (int) $ordem->previsao_entrega->diffInDays(today()) : '',
            (string) $ordem->nomeCliente(),
            (string) $ordem->telefoneCliente(),
            (string) $ordem->documentoCliente(),
            (string) $ordem->equipamento,
            (string) $ordem->marca,
            (string) $ordem->modelo,
            (string) $ordem->numero_serie,
            $ordem->tecnico->name ?? '',
            $ordem->prioridade === 'urgente' ? 'Urgente' : 'Normal',
            $ordem->statusTexto(),
            $ordem->concluida_em?->format('d/m/Y H:i') ?? '',
            $ordem->entregue_em?->format('d/m/Y H:i') ?? '',
            $ordem->garantia_dias !== null ? (int) $ordem->garantia_dias : '',
            (string) $ordem->defeito_relatado,
            (string) $ordem->laudo_tecnico,
            (string) $ordem->servico_executado,
        ];
    }

    private function dadosPeca($item): array
    {
        return [
            (string) $item->codigo_barras,
            (string) $item->descricao,
            (string) $item->unidade,
            (float) $item->quantidade,
            (float) $item->valor_unitario,
            $item->total(),
        ];
    }

    private function valoresOs($ordem): array
    {
        return [
            $ordem->valorPecas(),
            (float) $ordem->valor_servico,
            (float) $ordem->desconto,
            (float) $ordem->valor_total,
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

        foreach (['Valor unit.', 'Total da peça', 'Peças', 'Mão de obra', 'Desconto', 'Total da OS'] as $coluna) {
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
