<?php

namespace App\Http\Controllers\Relatorios;

use App\Exports\ComparativoDiarioVendasExport;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Relatórios > Gráficos > Comparativo diário de vendas
 *
 * Igual aos comparativos mensal e semanal, mas por dia: gráfico de barras verticais no topo e a tabela de cada dia abaixo.
 * Dia pela data de emissão. Dias sem venda aparecem zerados. Base: NFC-e e NF-e de saída emitidas.
 *
 * Comparação opcional (segunda barra no gráfico e colunas extras na tabela):
 *   semana = mesmo dia da semana anterior (7 dias antes)
 *   ano    = mesmo dia da semana, um ano antes (364 dias antes, para cair no mesmo dia da semana)
 */
class ComparativoDiarioVendasController extends Controller
{
    private const MAXIMO_DIAS = 90;
    private const PADRAO_DIAS = 30;

    private const METRICAS = [
        'liquido' => 'Valor líquido',
        'bruto' => 'Valor bruto',
        'documentos' => 'Nº de documentos',
        'ticket' => 'Ticket médio',
    ];

    private const COMPARACOES = [
        'nao' => 'Não',
        'semana' => 'Mesmo dia da semana anterior',
        'ano' => 'Mesmo dia da semana, um ano antes',
    ];

    private const DIAS_CURTOS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    private const DIAS_LONGOS = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $dados = $this->dados($filtros) + ['filtros' => $filtros, 'metricas' => self::METRICAS, 'comparacoes' => self::COMPARACOES];

        if ($request->ajax()) {
            return view('relatorios.graficos.comparativo-diario._conteudo', $dados);
        }

        return view('relatorios.graficos.comparativo-diario.index', $dados);
    }

    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $dados = $this->dados($filtros);

        return Excel::download(
            new ComparativoDiarioVendasExport(collect($dados['dias']), $dados['totais'], $filtros['comparar'] !== 'nao', $dados['comparacao_titulo']),
            'comparativo-diario-vendas-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportarPdf(Request $request)
    {
        $filtros = $this->filtros($request);
        $dados = $this->dados($filtros) + [
            'filtros' => $filtros,
            'metricas' => self::METRICAS,
            'resumoFiltros' => $this->resumoFiltros($filtros),
        ];

        return Pdf::loadView('relatorios.graficos.comparativo-diario.pdf', $dados)
            ->setPaper('a4', 'landscape')
            ->download('comparativo-diario-vendas-' . now()->format('Ymd-His') . '.pdf');
    }

    private function filtros(Request $request): array
    {
        $hoje = now()->startOfDay();

        $fim = $this->dataValida($request->get('data_fim')) ?? $hoje->copy();
        if ($fim > $hoje) {
            $fim = $hoje->copy();
        }

        $inicio = $this->dataValida($request->get('data_inicio')) ?? $fim->copy()->subDays(self::PADRAO_DIAS - 1);

        // Início depois do fim: usa só o dia do fim. Período grande demais: fica com os últimos dias.
        if ($inicio > $fim) {
            $inicio = $fim->copy();
        }

        if ($inicio->diffInDays($fim) >= self::MAXIMO_DIAS) {
            $inicio = $fim->copy()->subDays(self::MAXIMO_DIAS - 1);
        }

        return [
            'data_inicio' => $inicio->format('Y-m-d'),
            'data_fim' => $fim->format('Y-m-d'),
            'tipo' => in_array($request->get('tipo'), ['todos', 'nfce', 'nfe'], true) ? $request->get('tipo') : 'todos',
            'metrica' => array_key_exists((string) $request->get('metrica'), self::METRICAS) ? $request->get('metrica') : 'liquido',
            'comparar' => array_key_exists((string) $request->get('comparar'), self::COMPARACOES) ? $request->get('comparar') : 'nao',
        ];
    }

    private function dataValida($valor): ?Carbon
    {
        if (! is_string($valor) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }

        $data = Carbon::createFromFormat('!Y-m-d', $valor);

        return ($data && $data->format('Y-m-d') === $valor) ? $data : null;
    }

    /** Linhas de cada dia, totais do período e a geometria do gráfico. */
    private function dados(array $f): array
    {
        $inicio = Carbon::createFromFormat('!Y-m-d', $f['data_inicio']);
        $fim = Carbon::createFromFormat('!Y-m-d', $f['data_fim']);
        $hoje = now()->format('Y-m-d');

        // Quantos dias para trás fica o dia de comparação (0 = sem comparação)
        $recuo = match ($f['comparar']) {
            'semana' => 7,
            'ano' => 364,
            default => 0,
        };
        $comparar = $recuo > 0;

        // Busca um dia antes do início (para a variação do primeiro dia) e, se pedido, o período de comparação
        $atual = $this->diario($inicio->copy()->subDay(), $fim, $f['tipo']);
        $comparacao = $comparar
            ? $this->diario($inicio->copy()->subDays($recuo), $fim->copy()->subDays($recuo), $f['tipo'])
            : collect();

        $zero = $this->diaZerado();
        $dias = [];

        for ($dia = $inicio->copy(); $dia <= $fim; $dia->addDay()) {
            $chave = $dia->format('Y-m-d');
            $linha = (array) ($atual->get($chave) ?? $zero);
            $anterior = (array) ($atual->get($dia->copy()->subDay()->format('Y-m-d')) ?? $zero);
            $semana = $dia->dayOfWeek;

            $linha['chave'] = $chave;
            $linha['rotulo'] = self::DIAS_CURTOS[$semana] . ' ' . $dia->format('d/m/Y');
            $linha['rotulo_curto'] = $dia->format('d/m');
            $linha['rotulo_longo'] = self::DIAS_LONGOS[$semana] . ', ' . $dia->format('d/m/Y');
            $linha['fim_de_semana'] = in_array($semana, [0, 6], true);
            $linha['parcial'] = $chave === $hoje;

            $linha['variacao_valor'] = $linha['valor_liquido'] - $anterior['valor_liquido'];
            $linha['variacao_percentual'] = $anterior['valor_liquido'] > 0
                ? $linha['variacao_valor'] / $anterior['valor_liquido'] * 100 : null;

            if ($comparar) {
                $passado = $dia->copy()->subDays($recuo);
                $outro = (array) ($comparacao->get($passado->format('Y-m-d')) ?? $zero);
                $linha['comparacao'] = $outro;
                $linha['comparacao_rotulo'] = self::DIAS_CURTOS[$passado->dayOfWeek] . ' ' . $passado->format('d/m/Y');
                $linha['variacao_comparacao_percentual'] = $outro['valor_liquido'] > 0
                    ? ($linha['valor_liquido'] - $outro['valor_liquido']) / $outro['valor_liquido'] * 100 : null;
            }

            $dias[] = $linha;
        }

        $totais = $this->totais($dias);

        $totalLiquido = $totais->valor_liquido;
        foreach ($dias as &$linha) {
            $linha['participacao'] = $totalLiquido > 0 ? $linha['valor_liquido'] / $totalLiquido * 100 : 0;
        }
        unset($linha);

        $titulo = match ($f['comparar']) {
            'semana' => 'Mesmo dia da semana anterior',
            'ano' => 'Mesmo dia da semana, um ano antes',
            default => '',
        };

        return [
            'dias' => $dias,
            'totais' => $totais,
            'grafico' => $this->grafico($dias, $f['metrica'], $comparar, $titulo),
            'comparacao_titulo' => $titulo,
            'comparacao_curto' => $f['comparar'] === 'semana' ? 'semana ant.' : 'ano ant.',
        ];
    }

    private function diaZerado(): object
    {
        return (object) [
            'documentos' => 0, 'itens' => 0, 'valor_bruto' => 0.0, 'desconto' => 0.0, 'acrescimo' => 0.0,
            'valor_liquido' => 0.0, 'liquido_nfce' => 0.0, 'liquido_nfe' => 0.0, 'ticket_medio' => 0.0,
        ];
    }

    /** Totais de cada dia entre $de e $ate (inclusive), indexados por "AAAA-MM-DD". Só os dias que tiveram venda. */
    private function diario(Carbon $de, Carbon $ate, string $tipo): Collection
    {
        $nfce = DB::table('venda_itens as i')
            ->join('vendas as d', 'd.id', '=', 'i.venda_id')
            ->where('d.status', 'emitida')
            ->selectRaw("
                'NFC-e' COLLATE utf8mb4_unicode_ci as tipo,
                d.id as documento_id,
                d.emitida_em as data,
                COALESCE(i.valor_bruto, i.preco_unitario * i.quantidade) as valor_bruto,
                COALESCE(i.desconto_efetivo, i.desconto, 0) as desconto,
                0 as acrescimo,
                (COALESCE(i.valor_bruto, i.preco_unitario * i.quantidade) - COALESCE(i.desconto_efetivo, i.desconto, 0)) as valor_liquido
            ");

        $nfe = DB::table('nota_fiscal_itens as i')
            ->join('notas_fiscais as d', 'd.id', '=', 'i.nota_fiscal_id')
            ->where('d.status', 'emitida')
            ->where('d.tipo_operacao', '<>', 'entrada')
            ->where('d.finalidade', 1) // só NF-e normal: devolução, complementar e ajuste não são venda
            ->selectRaw("
                'NF-e' COLLATE utf8mb4_unicode_ci as tipo,
                d.id as documento_id,
                d.emitida_em as data,
                (i.valor_unitario * i.quantidade + i.valor_frete + i.valor_outras_despesas) as valor_bruto,
                COALESCE(i.desconto_efetivo, i.valor_desconto, 0) as desconto,
                (i.valor_frete + i.valor_outras_despesas) as acrescimo,
                ((i.valor_unitario * i.quantidade + i.valor_frete + i.valor_outras_despesas) - COALESCE(i.desconto_efetivo, i.valor_desconto, 0)) as valor_liquido
            ");

        $inicio = $de->copy()->startOfDay()->format('Y-m-d H:i:s');
        $limite = $ate->copy()->endOfDay()->format('Y-m-d H:i:s');

        foreach ([$nfce, $nfe] as $q) {
            $q->where('d.emitida_em', '>=', $inicio)->where('d.emitida_em', '<=', $limite);
        }

        $lados = match ($tipo) {
            'nfce' => $nfce,
            'nfe' => $nfe,
            default => $nfce->unionAll($nfe),
        };

        return DB::query()->fromSub($lados, 'r')->selectRaw("
            DATE_FORMAT(r.data, '%Y-%m-%d') as dia,
            COUNT(DISTINCT r.tipo, r.documento_id) as documentos,
            COUNT(*) as itens,
            COALESCE(SUM(r.valor_bruto), 0) as valor_bruto,
            COALESCE(SUM(r.desconto), 0) as desconto,
            COALESCE(SUM(r.acrescimo), 0) as acrescimo,
            COALESCE(SUM(r.valor_liquido), 0) as valor_liquido,
            COALESCE(SUM(CASE WHEN r.tipo = 'NFC-e' THEN r.valor_liquido END), 0) as liquido_nfce,
            COALESCE(SUM(CASE WHEN r.tipo = 'NF-e' THEN r.valor_liquido END), 0) as liquido_nfe
        ")->groupBy('dia')->get()->map(function ($l) {
            $l->documentos = (int) $l->documentos;
            $l->itens = (int) $l->itens;
            foreach (['valor_bruto', 'desconto', 'acrescimo', 'valor_liquido', 'liquido_nfce', 'liquido_nfe'] as $c) {
                $l->{$c} = (float) $l->{$c};
            }
            $l->ticket_medio = $l->documentos > 0 ? $l->valor_liquido / $l->documentos : 0.0;

            return $l;
        })->keyBy('dia');
    }

    private function totais(array $dias): object
    {
        $c = collect($dias);

        $t = (object) [
            'dias' => count($dias),
            'dias_com_venda' => $c->filter(fn ($d) => $d['documentos'] > 0)->count(),
            'documentos' => (int) $c->sum('documentos'),
            'itens' => (int) $c->sum('itens'),
            'valor_bruto' => (float) $c->sum('valor_bruto'),
            'desconto' => (float) $c->sum('desconto'),
            'acrescimo' => (float) $c->sum('acrescimo'),
            'valor_liquido' => (float) $c->sum('valor_liquido'),
            'liquido_nfce' => (float) $c->sum('liquido_nfce'),
            'liquido_nfe' => (float) $c->sum('liquido_nfe'),
        ];

        $t->ticket_medio = $t->documentos > 0 ? $t->valor_liquido / $t->documentos : 0.0;
        $t->media_diaria = $t->dias > 0 ? $t->valor_liquido / $t->dias : 0.0;

        $melhor = $c->sortByDesc('valor_liquido')->first();
        $t->melhor_dia = ($melhor && $melhor['valor_liquido'] > 0) ? $melhor['rotulo'] : null;
        $t->melhor_valor = $melhor['valor_liquido'] ?? 0.0;

        $ultimo = $c->last();
        $t->ultimo_rotulo = $ultimo['rotulo'] ?? null;
        $t->ultimo_variacao = $ultimo['variacao_percentual'] ?? null;
        $t->ultimo_parcial = (bool) ($ultimo['parcial'] ?? false);

        // Total dos dias de comparação (só quando a comparação está ligada)
        $t->comparacao = $c->contains(fn ($d) => isset($d['comparacao']))
            ? (float) $c->sum(fn ($d) => $d['comparacao']['valor_liquido'] ?? 0)
            : null;
        $t->variacao_comparacao = ($t->comparacao !== null && $t->comparacao > 0)
            ? ($t->valor_liquido - $t->comparacao) / $t->comparacao * 100 : null;

        return $t;
    }

    /** Valor do dia na métrica escolhida (a mesma do gráfico). */
    private function valorDaMetrica(array $dados, string $metrica): float
    {
        return (float) match ($metrica) {
            'bruto' => $dados['valor_bruto'],
            'documentos' => $dados['documentos'],
            'ticket' => $dados['ticket_medio'],
            default => $dados['valor_liquido'],
        };
    }

    /** Geometria do gráfico de barras verticais (SVG): eixos, barras, rótulos e tooltips já calculados. */
    private function grafico(array $dias, string $metrica, bool $comparar, string $tituloComparacao): array
    {
        $n = count($dias);
        $ehMoeda = $metrica !== 'documentos';

        $altura = 340;
        $topo = 28;
        $base = 56;
        $x0 = 78;
        $areaAltura = $altura - $topo - $base;
        $yBase = $topo + $areaAltura;
        $slot = $comparar ? 54 : 34;
        $larguraBarra = $comparar ? 20 : 22;
        $largura = max(680, $x0 + $n * $slot + 16);

        $vazia = ['valor_bruto' => 0, 'documentos' => 0, 'ticket_medio' => 0, 'valor_liquido' => 0];

        $valores = [];
        foreach ($dias as $i => $d) {
            $valores[$i] = $this->valorDaMetrica($d, $metrica);
            if ($comparar) {
                $valores[$i . 'c'] = $this->valorDaMetrica($d['comparacao'] ?? $vazia, $metrica);
            }
        }

        [$maximo, $passo] = $this->escala(max($valores ?: [0]));

        $ticks = [];
        for ($k = 0; $k <= 4; $k++) {
            $valor = $passo * $k;
            $ticks[] = [
                'y' => round($yBase - ($maximo > 0 ? $valor / $maximo * $areaAltura : 0), 2),
                'rotulo' => $this->rotuloCompacto($valor, $ehMoeda),
            ];
        }

        $mostrarValores = $n <= ($comparar ? 7 : 12);
        $maiorValor = max(array_map(fn ($i) => $valores[$i], array_keys($dias)) ?: [0]);

        $barras = [];
        foreach ($dias as $i => $d) {
            $centro = $x0 + $i * $slot + $slot / 2;
            $v = $valores[$i];
            $h = $maximo > 0 ? $v / $maximo * $areaAltura : 0;

            $barra = [
                'rotulo' => $d['rotulo_curto'],
                'x_rotulo' => round($centro, 2),
                'y_rotulo' => $yBase + 20,
                'x' => round($comparar ? $centro + 1 : $centro - $larguraBarra / 2, 2),
                'y' => round($yBase - $h, 2),
                'w' => $larguraBarra,
                'h' => round($h, 2),
                'valor_rotulo' => $this->rotuloCompacto($v, $ehMoeda),
                'x_valor' => round($comparar ? $centro + 1 + $larguraBarra / 2 : $centro, 2),
                'y_valor' => round($yBase - $h - 6, 2),
                'melhor' => $maiorValor > 0 && $v === $maiorValor,
                'parcial' => $d['parcial'],
                'tooltip' => $d['rotulo_longo'] . ': ' . $this->valorCompleto($v, $ehMoeda) . ($d['parcial'] ? ' (dia em andamento)' : ''),
            ];

            if ($comparar) {
                $vc = $valores[$i . 'c'];
                $hc = $maximo > 0 ? $vc / $maximo * $areaAltura : 0;
                $barra['anterior'] = [
                    'x' => round($centro - $larguraBarra - 1, 2),
                    'y' => round($yBase - $hc, 2),
                    'w' => $larguraBarra,
                    'h' => round($hc, 2),
                    'tooltip' => $tituloComparacao . ', ' . ($d['comparacao_rotulo'] ?? '') . ': ' . $this->valorCompleto($vc, $ehMoeda),
                ];
            }

            $barras[] = $barra;
        }

        return [
            'largura' => $largura,
            'altura' => $altura,
            'x0' => $x0,
            'x1' => $largura - 8,
            'y_base' => $yBase,
            'ticks' => $ticks,
            'barras' => $barras,
            'comparar' => $comparar,
            'mostrar_valores' => $mostrarValores,
            'metrica' => self::METRICAS[$metrica],
            'legenda_melhor' => 'Melhor dia',
            'legenda_parcial' => 'Dia em andamento',
            'legenda_anterior' => $tituloComparacao,
            'vazio' => array_sum($valores) <= 0,
        ];
    }

    /** Máximo "redondo" do eixo e o passo entre as 4 divisões (ex.: 12.300 → 15.000 de 3.750 em 3.750). */
    private function escala(float $maximo): array
    {
        if ($maximo <= 0) {
            return [4.0, 1.0];
        }

        $magnitude = 10 ** floor(log10($maximo / 4));

        foreach ([1, 2, 2.5, 5, 10] as $fator) {
            $passo = $fator * $magnitude;

            if ($passo * 4 >= $maximo) {
                return [$passo * 4, $passo];
            }
        }

        return [$maximo, $maximo / 4];
    }

    private function rotuloCompacto(float $v, bool $moeda): string
    {
        $prefixo = $moeda ? 'R$ ' : '';

        if ($v >= 1_000_000) {
            return $prefixo . rtrim(rtrim(number_format($v / 1_000_000, 1, ',', ''), '0'), ',') . ' mi';
        }
        if ($v >= 1_000) {
            return $prefixo . rtrim(rtrim(number_format($v / 1_000, 1, ',', ''), '0'), ',') . ' mil';
        }

        return $prefixo . rtrim(rtrim(number_format($v, $moeda ? 2 : 0, ',', ''), '0'), ',');
    }

    private function valorCompleto(float $v, bool $moeda): string
    {
        return $moeda ? 'R$ ' . number_format($v, 2, ',', '.') : number_format($v, 0, ',', '.');
    }

    private function resumoFiltros(array $f): array
    {
        $dia = fn (string $d) => date('d/m/Y', strtotime($d));

        return [
            'Período: ' . $dia($f['data_inicio']) . ' a ' . $dia($f['data_fim']),
            'Documentos: ' . ['todos' => 'NFC-e e NF-e', 'nfce' => 'Só NFC-e', 'nfe' => 'Só NF-e'][$f['tipo']],
            'Gráfico: ' . self::METRICAS[$f['metrica']],
            'Comparação: ' . self::COMPARACOES[$f['comparar']],
        ];
    }
}
