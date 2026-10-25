<?php

namespace App\Http\Controllers\Relatorios;

use App\Exports\ComparativoMensalVendasExport;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Relatórios > Gráficos > Comparativo mensal de vendas
 *
 * Gráfico de barras verticais com a evolução das vendas mês a mês e, logo abaixo, a tabela de cada mês
 * (documentos, valores, ticket médio, variação sobre o mês anterior e, se pedido, sobre o mesmo mês do ano anterior).
 * Base: NFC-e e NF-e de saída emitidas, pela data de emissão. Os meses sem venda aparecem zerados.
 *
 * O gráfico é um SVG montado aqui (sem biblioteca de gráficos): funciona sem internet, no PDF e só com Tailwind.
 */
class ComparativoMensalVendasController extends Controller
{
    private const MAXIMO_MESES = 36;

    private const METRICAS = [
        'liquido' => 'Valor líquido',
        'bruto' => 'Valor bruto',
        'documentos' => 'Nº de documentos',
        'ticket' => 'Ticket médio',
    ];

    private const MESES_CURTOS = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    private const MESES_LONGOS = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $dados = $this->dados($filtros) + ['filtros' => $filtros, 'metricas' => self::METRICAS];

        if ($request->ajax()) {
            return view('relatorios.graficos.comparativo-mensal._conteudo', $dados);
        }

        return view('relatorios.graficos.comparativo-mensal.index', $dados);
    }

    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $dados = $this->dados($filtros);

        return Excel::download(
            new ComparativoMensalVendasExport(collect($dados['meses']), $dados['totais'], $filtros['comparar'] === 'sim'),
            'comparativo-mensal-vendas-' . now()->format('Ymd-His') . '.xlsx'
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

        return Pdf::loadView('relatorios.graficos.comparativo-mensal.pdf', $dados)
            ->setPaper('a4', 'landscape')
            ->download('comparativo-mensal-vendas-' . now()->format('Ymd-His') . '.pdf');
    }

    private function filtros(Request $request): array
    {
        $fim = $this->mesValido($request->get('mes_fim')) ?? now()->format('Y-m');
        $inicio = $this->mesValido($request->get('mes_inicio')) ?? Carbon::createFromFormat('!Y-m-d', $fim . '-01')->subMonths(11)->format('Y-m');

        // Início depois do fim: usa só o mês do fim. Período grande demais: fica com os últimos meses.
        if ($inicio > $fim) {
            $inicio = $fim;
        }

        $primeiro = Carbon::createFromFormat('!Y-m-d', $inicio . '-01');
        $ultimo = Carbon::createFromFormat('!Y-m-d', $fim . '-01');

        if ($primeiro->diffInMonths($ultimo) >= self::MAXIMO_MESES) {
            $inicio = $ultimo->copy()->subMonths(self::MAXIMO_MESES - 1)->format('Y-m');
        }

        return [
            'mes_inicio' => $inicio,
            'mes_fim' => $fim,
            'tipo' => in_array($request->get('tipo'), ['todos', 'nfce', 'nfe'], true) ? $request->get('tipo') : 'todos',
            'metrica' => array_key_exists((string) $request->get('metrica'), self::METRICAS) ? $request->get('metrica') : 'liquido',
            'comparar' => $request->get('comparar') === 'sim' ? 'sim' : 'nao',
        ];
    }

    private function mesValido($valor): ?string
    {
        if (! is_string($valor) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $valor)) {
            return null;
        }

        return $valor;
    }

    /** Linhas de cada mês, totais do período e a geometria do gráfico. */
    private function dados(array $f): array
    {
        $inicio = Carbon::createFromFormat('!Y-m-d', $f['mes_inicio'] . '-01')->startOfMonth();
        $fim = Carbon::createFromFormat('!Y-m-d', $f['mes_fim'] . '-01')->startOfMonth();
        $comparar = $f['comparar'] === 'sim';

        // Busca um mês antes do início (para a variação do primeiro mês) e, se pedido, o mesmo período um ano antes
        $atual = $this->mensal($inicio->copy()->subMonth(), $fim, $f['tipo']);
        $anoAnterior = $comparar
            ? $this->mensal($inicio->copy()->subYear(), $fim->copy()->subYear(), $f['tipo'])
            : collect();

        $zero = $this->mesZerado();
        $meses = [];

        for ($mes = $inicio->copy(); $mes <= $fim; $mes->addMonth()) {
            $chave = $mes->format('Y-m');
            $linha = (array) ($atual->get($chave) ?? $zero);
            $anterior = (array) ($atual->get($mes->copy()->subMonth()->format('Y-m')) ?? $zero);

            $linha['chave'] = $chave;
            $linha['rotulo'] = ucfirst(self::MESES_CURTOS[$mes->month - 1]) . '/' . $mes->year;
            $linha['rotulo_curto'] = self::MESES_CURTOS[$mes->month - 1] . '/' . $mes->format('y');
            $linha['rotulo_longo'] = self::MESES_LONGOS[$mes->month - 1] . ' de ' . $mes->year;
            $linha['parcial'] = $mes->format('Y-m') === now()->format('Y-m');

            $linha['variacao_valor'] = $linha['valor_liquido'] - $anterior['valor_liquido'];
            $linha['variacao_percentual'] = $anterior['valor_liquido'] > 0
                ? $linha['variacao_valor'] / $anterior['valor_liquido'] * 100 : null;

            if ($comparar) {
                $ano = (array) ($anoAnterior->get($mes->copy()->subYear()->format('Y-m')) ?? $zero);
                $linha['ano_anterior'] = $ano;
                $linha['variacao_ano_percentual'] = $ano['valor_liquido'] > 0
                    ? ($linha['valor_liquido'] - $ano['valor_liquido']) / $ano['valor_liquido'] * 100 : null;
            }

            $meses[] = $linha;
        }

        $totais = $this->totais($meses);

        $totalLiquido = $totais->valor_liquido;
        foreach ($meses as &$linha) {
            $linha['participacao'] = $totalLiquido > 0 ? $linha['valor_liquido'] / $totalLiquido * 100 : 0;
        }
        unset($linha);

        return [
            'meses' => $meses,
            'totais' => $totais,
            'grafico' => $this->grafico($meses, $f['metrica'], $comparar),
        ];
    }

    private function mesZerado(): object
    {
        return (object) [
            'documentos' => 0, 'itens' => 0, 'valor_bruto' => 0.0, 'desconto' => 0.0, 'acrescimo' => 0.0,
            'valor_liquido' => 0.0, 'liquido_nfce' => 0.0, 'liquido_nfe' => 0.0, 'ticket_medio' => 0.0,
        ];
    }

    /** Totais de cada mês entre $de e $ate (inclusive), indexados por "AAAA-MM". Só os meses que tiveram venda. */
    private function mensal(Carbon $de, Carbon $ate, string $tipo): Collection
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

        $inicio = $de->copy()->startOfMonth()->format('Y-m-d H:i:s');
        $limite = $ate->copy()->endOfMonth()->format('Y-m-d H:i:s');

        foreach ([$nfce, $nfe] as $q) {
            $q->where('d.emitida_em', '>=', $inicio)->where('d.emitida_em', '<=', $limite);
        }

        $lados = match ($tipo) {
            'nfce' => $nfce,
            'nfe' => $nfe,
            default => $nfce->unionAll($nfe),
        };

        return DB::query()->fromSub($lados, 'r')->selectRaw("
            DATE_FORMAT(r.data, '%Y-%m') as mes,
            COUNT(DISTINCT r.tipo, r.documento_id) as documentos,
            COUNT(*) as itens,
            COALESCE(SUM(r.valor_bruto), 0) as valor_bruto,
            COALESCE(SUM(r.desconto), 0) as desconto,
            COALESCE(SUM(r.acrescimo), 0) as acrescimo,
            COALESCE(SUM(r.valor_liquido), 0) as valor_liquido,
            COALESCE(SUM(CASE WHEN r.tipo = 'NFC-e' THEN r.valor_liquido END), 0) as liquido_nfce,
            COALESCE(SUM(CASE WHEN r.tipo = 'NF-e' THEN r.valor_liquido END), 0) as liquido_nfe
        ")->groupBy('mes')->get()->map(function ($l) {
            $l->documentos = (int) $l->documentos;
            $l->itens = (int) $l->itens;
            foreach (['valor_bruto', 'desconto', 'acrescimo', 'valor_liquido', 'liquido_nfce', 'liquido_nfe'] as $c) {
                $l->{$c} = (float) $l->{$c};
            }
            $l->ticket_medio = $l->documentos > 0 ? $l->valor_liquido / $l->documentos : 0.0;

            return $l;
        })->keyBy('mes');
    }

    private function totais(array $meses): object
    {
        $c = collect($meses);

        $t = (object) [
            'meses' => count($meses),
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
        $t->media_mensal = $t->meses > 0 ? $t->valor_liquido / $t->meses : 0.0;

        $melhor = $c->sortByDesc('valor_liquido')->first();
        $t->melhor_mes = ($melhor && $melhor['valor_liquido'] > 0) ? $melhor['rotulo'] : null;
        $t->melhor_valor = $melhor['valor_liquido'] ?? 0.0;

        $ultimo = $c->last();
        $t->ultimo_rotulo = $ultimo['rotulo'] ?? null;
        $t->ultimo_variacao = $ultimo['variacao_percentual'] ?? null;
        $t->ultimo_parcial = (bool) ($ultimo['parcial'] ?? false);

        // Total do ano anterior para o período inteiro (só quando a comparação está ligada)
        $t->ano_anterior = $c->contains(fn ($m) => isset($m['ano_anterior']))
            ? (float) $c->sum(fn ($m) => $m['ano_anterior']['valor_liquido'] ?? 0)
            : null;
        $t->variacao_ano = ($t->ano_anterior !== null && $t->ano_anterior > 0)
            ? ($t->valor_liquido - $t->ano_anterior) / $t->ano_anterior * 100 : null;

        return $t;
    }

    /** Valor do mês na métrica escolhida (a mesma do gráfico). */
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
    private function grafico(array $meses, string $metrica, bool $comparar): array
    {
        $n = count($meses);
        $ehMoeda = $metrica !== 'documentos';

        $altura = 340;
        $topo = 28;
        $base = 56;
        $x0 = 78;
        $areaAltura = $altura - $topo - $base;
        $yBase = $topo + $areaAltura;
        $slot = $comparar ? 76 : 60;
        $larguraBarra = $comparar ? 27 : 38;
        $largura = max(680, $x0 + $n * $slot + 16);

        $valores = [];
        foreach ($meses as $i => $m) {
            $valores[$i] = $this->valorDaMetrica($m, $metrica);
            if ($comparar) {
                $valores[$i . 'a'] = $this->valorDaMetrica($m['ano_anterior'] ?? [
                    'valor_bruto' => 0, 'documentos' => 0, 'ticket_medio' => 0, 'valor_liquido' => 0,
                ], $metrica);
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

        $mostrarValores = $n <= ($comparar ? 9 : 15);
        $maiorValor = max(array_map(fn ($i) => $valores[$i], array_keys($meses)) ?: [0]);

        $barras = [];
        foreach ($meses as $i => $m) {
            $centro = $x0 + $i * $slot + $slot / 2;
            $v = $valores[$i];
            $h = $maximo > 0 ? $v / $maximo * $areaAltura : 0;

            $barra = [
                'rotulo' => $m['rotulo_curto'],
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
                'parcial' => $m['parcial'],
                'tooltip' => $m['rotulo_longo'] . ': ' . $this->valorCompleto($v, $ehMoeda) . ($m['parcial'] ? ' (mês em andamento)' : ''),
            ];

            if ($comparar) {
                $va = $valores[$i . 'a'];
                $ha = $maximo > 0 ? $va / $maximo * $areaAltura : 0;
                $barra['anterior'] = [
                    'x' => round($centro - $larguraBarra - 1, 2),
                    'y' => round($yBase - $ha, 2),
                    'w' => $larguraBarra,
                    'h' => round($ha, 2),
                    'tooltip' => 'Mesmo mês do ano anterior (' . ($m['rotulo_longo'] ? preg_replace('/\d{4}$/', (string) (((int) substr($m['chave'], 0, 4)) - 1), $m['rotulo_longo']) : '') . '): ' . $this->valorCompleto($va, $ehMoeda),
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
        $mes = fn (string $m) => self::MESES_LONGOS[(int) substr($m, 5, 2) - 1] . '/' . substr($m, 0, 4);

        return [
            'Período: ' . $mes($f['mes_inicio']) . ' a ' . $mes($f['mes_fim']),
            'Documentos: ' . ['todos' => 'NFC-e e NF-e', 'nfce' => 'Só NFC-e', 'nfe' => 'Só NF-e'][$f['tipo']],
            'Gráfico: ' . self::METRICAS[$f['metrica']],
            'Comparação com o ano anterior: ' . ($f['comparar'] === 'sim' ? 'sim' : 'não'),
        ];
    }
}
