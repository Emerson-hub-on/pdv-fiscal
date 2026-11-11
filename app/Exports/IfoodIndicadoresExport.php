<?php

namespace App\Exports;

use App\Services\IfoodIndicadoresService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Indicadores iFood em abas: resumo e tempos, por dia, por hora, mais vendidos e cancelamentos. */
class IfoodIndicadoresExport implements Export, WithMultipleSheets
{
    public function __construct(private array $dados, private array $filtros)
    {
    }

    public function sheets(): array
    {
        $d = $this->dados;
        $r = $d['resumo'];
        $periodo = Carbon::parse($this->filtros['data_inicio'])->format('d/m/Y') . ' a ' . Carbon::parse($this->filtros['data_fim'])->format('d/m/Y');
        $min = fn ($v) => $v === null ? '' : round($v, 1);

        $resumo = [
            ['Período', $periodo],
            ['Tipo', IfoodIndicadoresService::TIPOS[$this->filtros['tipo']] ?? 'Todos'],
            ['Pedidos recebidos', $r['recebidos']],
            ['Pedidos válidos (sem cancelados)', $r['pedidos']],
            ['Concluídos', $r['concluidos']],
            ['Faturamento', $r['faturamento']],
            ['Ticket médio', $r['ticket'] ?? ''],
            ['Cancelados', $r['cancelados']],
            ['Cancelados pela loja', $r['cancelados_loja']],
            ['Valor cancelado', $r['valor_cancelado']],
            ['Taxa de cancelamento (%)', $r['taxa_cancelamento'] ?? ''],
            ['Horário de pico', $d['mapa']['pico']['texto'] ?? ''],
            [],
            ['Tempo (minutos)', 'Média', 'Mediana', 'Mais demorado', 'Pedidos na conta', 'Média do período anterior'],
        ];
        foreach ($d['tempos'] as $t) {
            $v = $t['valores'];
            $resumo[] = [$t['rotulo'] . ' (' . $t['explica'] . ')', $min($v['media'] ?? null), $min($v['mediana'] ?? null), $min($v['maximo'] ?? null), $v['pedidos'] ?? 0, $min($t['anterior'])];
        }

        return [
            $this->aba('Resumo', [], $resumo),
            $this->aba('Por dia', ['Dia', 'Dia da semana', 'Pedidos', 'Faturamento', 'Cancelados'],
                array_map(fn ($x) => [Carbon::parse($x['data'])->format('d/m/Y'), $x['semana'], $x['pedidos'], $x['faturamento'], $x['cancelados']], $d['por_dia'])),
            $this->aba('Por hora', ['Hora', 'Pedidos', 'Faturamento'],
                array_map(fn ($x) => [$x['rotulo'], $x['pedidos'], $x['faturamento']], $d['por_hora'])),
            $this->aba('Mais vendidos', ['Produto', 'Quantidade', 'Valor', 'Pedidos', '% do valor'],
                array_map(fn ($x) => [$x['nome'], $x['quantidade'], $x['valor'], $x['pedidos'], $x['participacao']], $d['produtos'])),
            $this->aba('Cancelamentos', ['Motivo', 'Pedidos', 'Valor'],
                array_map(fn ($x) => [$x['motivo'], $x['pedidos'], $x['valor']], $d['cancelamentos']['motivos'])),
        ];
    }

    private function aba(string $titulo, array $cabecalho, array $linhas): object
    {
        return new class($titulo, $cabecalho, $linhas) implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles {
            public function __construct(private string $titulo, private array $cabecalho, private array $linhas)
            {
            }

            public function array(): array
            {
                return $this->linhas;
            }

            public function headings(): array
            {
                return $this->cabecalho;
            }

            public function title(): string
            {
                return $this->titulo;
            }

            public function styles(Worksheet $sheet): array
            {
                return $this->cabecalho ? [1 => ['font' => ['bold' => true]]] : [];
            }
        };
    }
}
