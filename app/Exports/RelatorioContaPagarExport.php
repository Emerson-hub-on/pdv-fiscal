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
 * Detalhada: uma linha por PARCELA (dados da conta repetidos para filtrar/agrupar no Excel).
 *            Os totais da conta só aparecem na 1ª linha de cada conta, para a soma da coluna não duplicar.
 * Resumida:  uma linha por CONTA.
 */
class RelatorioContaPagarExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    ShouldAutoSize,
    WithStyles,
    WithColumnFormatting,
    WithCustomValueBinder
{
    private const COLUNAS_CONTA = [
        'Documento', 'Data da conta', 'Fornecedor', 'CPF/CNPJ', 'Tipo', 'Descrição', 'Origem', 'Situação da conta',
    ];

    private const COLUNAS_PARCELA = [
        'Parcela', 'Vencimento', 'Valor da parcela', 'Situação da parcela', 'Pago em', 'Valor pago',
        'Forma de pagamento', 'Dias de atraso',
    ];

    private const COLUNAS_TOTAIS_DETALHADA = ['Valor total', 'Total pago', 'Saldo em aberto'];

    private const COLUNAS_TOTAIS_RESUMIDA = ['Valor total', 'Total pago', 'Saldo em aberto', 'Próximo vencimento'];

    /** Colunas que precisam ficar como TEXTO. */
    private const TEXTO = ['Documento', 'CPF/CNPJ'];

    private array $cabecalhos;

    private ?array $letrasTexto = null;

    public function __construct(private Collection $contas, private bool $detalhada)
    {
        $this->cabecalhos = $detalhada
            ? array_merge(self::COLUNAS_CONTA, self::COLUNAS_PARCELA, self::COLUNAS_TOTAIS_DETALHADA)
            : array_merge(self::COLUNAS_CONTA, self::COLUNAS_TOTAIS_RESUMIDA);
    }

    public function headings(): array
    {
        return $this->cabecalhos;
    }

    public function collection(): Collection
    {
        $linhas = collect();

        foreach ($this->contas as $conta) {
            $dadosConta = $this->dadosConta($conta);

            if (! $this->detalhada) {
                $proxima = $conta->proximaParcela();

                $linhas->push(array_merge($dadosConta, $this->totaisConta($conta), [
                    $proxima?->vencimento?->format('d/m/Y') ?? '',
                ]));
                continue;
            }

            $totais = $this->totaisConta($conta);

            if ($conta->parcelas->isEmpty()) {
                $linhas->push(array_merge($dadosConta, array_fill(0, count(self::COLUNAS_PARCELA), ''), $totais));
                continue;
            }

            $semTotais = array_map(fn () => null, $totais);

            foreach ($conta->parcelas->sortBy('numero')->values() as $i => $parcela) {
                $linhas->push(array_merge(
                    $dadosConta,
                    $this->dadosParcela($parcela),
                    $i === 0 ? $totais : $semTotais
                ));
            }
        }

        return $linhas;
    }

    private function dadosConta($conta): array
    {
        return [
            $conta->numeroExibicao(),
            $conta->data_conta?->format('d/m/Y') ?? '',
            $conta->fornecedor->nome_exibicao ?? '',
            $conta->fornecedor->documento_formatado ?? '',
            $conta->tipo->descricao ?? '',
            (string) $conta->descricao,
            $conta->entrada_nota_id ? 'Entrada de nota' : 'Manual',
            match ($conta->situacao()) {
                'paga'    => 'Paga',
                'vencida' => 'Vencida',
                default   => 'Em aberto',
            },
        ];
    }

    private function dadosParcela($parcela): array
    {
        $situacao = $parcela->isPaga() ? 'Paga' : ($parcela->isVencida() ? 'Vencida' : 'A vencer');

        return [
            (int) $parcela->numero,
            $parcela->vencimento?->format('d/m/Y') ?? '',
            (float) $parcela->valor,
            $situacao,
            $parcela->data_pagamento?->format('d/m/Y') ?? '',
            $parcela->isPaga() ? (float) ($parcela->valor_pago ?? $parcela->valor) : null,
            $parcela->formaPagamento->descricao ?? '',
            self::diasAtraso($parcela) ?: '',
        ];
    }

    /** Em aberto e vencida: dias desde o vencimento. Paga: dias que atrasou até pagar (0 se em dia). */
    public static function diasAtraso($parcela): int
    {
        if (! $parcela->vencimento) {
            return 0;
        }

        $fim = $parcela->isPaga() ? $parcela->data_pagamento : today();

        return max((int) $parcela->vencimento->diffInDays($fim, false), 0);
    }

    private function totaisConta($conta): array
    {
        $pago = (float) $conta->parcelas->filter(fn ($p) => $p->isPaga())->sum(fn ($p) => $p->valor_pago ?? $p->valor);

        return [
            (float) $conta->valor_total,
            $pago,
            $conta->saldoAberto(),
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

        foreach (['Valor da parcela', 'Valor pago', 'Valor total', 'Total pago', 'Saldo em aberto'] as $coluna) {
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
