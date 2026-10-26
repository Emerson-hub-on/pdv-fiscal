<?php

namespace App\Http\Controllers\Relatorios;

use App\Exports\ComparativoSemanalVendasExport;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Relatórios > Gráficos > Comparativo semanal de vendas
 *
 * Igual ao comparativo mensal, mas por semana: gráfico de barras verticais no topo e a tabela de cada semana abaixo.
 *
 * Semana = segunda a domingo (semana ISO), pela data de emissão. O período escolhido é ajustado para semanas inteiras:
 * entra a semana que contém a data inicial até a que contém a data final. Semanas sem venda aparecem zeradas.
 * "Mesma semana do ano anterior" = 52 semanas (364 dias) antes, para cair no mesmo dia da semana.
 * Base: NFC-e e NF-e de saída emitidas.
 */
class ComparativoSemanalVendasController extends Controller
{
    private const MAXIMO_SEMANAS = 52;
    private const PADRAO_SEMANAS = 12;

    private const METRICAS = [
        'liquido' => 'Valor líquido',
        'bruto' => 'Valor bruto',
        'documentos' => 'Nº de documentos',
        'ticket' => 'Ticket médio',
    ];

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $dados = $this->dados($filtros) + ['filtros' => $filtros, 'metricas' => self::METRICAS];

        if ($request->ajax()) {
            return view('relatorios.graficos.comparativo-semanal._conteudo', $dados);
        }

        return view('relatorios.graficos.comparativo-semanal.index', $dados);
    }

    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $dados = $this->dados($filtros);

        return Excel::download(
            new ComparativoSemanalVendasExport(collect($dados['semanas']), $dados['totais'], $filtros['comparar'] === 'sim'),
            'comparativo-semanal-vendas-' . now()->format('Ymd-His') . '.xlsx'
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

        return Pdf::loadView('relatorios.graficos.comparativo-semanal.pdf', $dados)
            ->setPaper('a4', 'landscape')
            ->download('comparativo-semanal-vendas-' . now()->format('Ymd-His') . '.pdf');
    }

    private function filtros(Request $request): array
    {
        $hoje = now()->startOfDay();

        $fim = $this->dataValida($request->get('data_fim')) ?? $hoje->copy();
        if ($fim > $hoje) {
            $fim = $hoje->copy();
        }

        $inicio = $this->dataValida($request->get('data_inicio'))
            ?? $fim->copy()->startOfWeek(Carbon::MONDAY)->subWeeks(self::PADRAO_SEMANAS - 1);

        // Início depois do fim: usa só a semana do fim. Período grande demais: fica com as últimas semanas.
        if ($inicio > $fim) {
            $inicio = $fim->copy();
        }

        $primeira = $inicio->copy()->startOfWeek(Carbon::MONDAY);
        $ultima = $fim->copy()->startOfWeek(Carbon::MONDAY);

        if ($primeira->diffInWeeks($ultima) >= self::MAXIMO_SEMANAS) {
            $inicio = $ultima->copy()->subWeeks(self::MAXIMO_SEMANAS - 1);
        }

        return [
            'data_inicio' => $inicio->format('Y-m-d'),
            'data_fim' => $fim->format('Y-m-d'),
            'tipo' => in_array($request->get('tipo'), ['todos', 'nfce', 'nfe'], true) ? $request->get('tipo') : 'todos',
            'metrica' => array_key_exists((string) $request->get('metrica'), self::METRICAS) ? $request->get('metrica') : 'liquido',
            'comparar' => $request->get('comparar') === 'sim' ? 'sim' : 'nao',
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

    /** Linhas de cada semana, totais do período e a geometria do gráfico. */
    private function dados(array $f): array
    {
        $inicio = Carbon::createFromFormat('!Y-m-d', $f['data_inicio'])->startOfWeek(Carbon::MONDAY);
        $fim = Carbon::createFromFormat('!Y-m-d', $f['data_fim'])->startOfWeek(Carbon::MONDAY);
        $comparar = $f['comparar'] === 'sim';
        $semanaAtual = now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');

        // Busca uma semana antes do início (para a variação da primeira) e, se pedido, as mesmas 52 semanas antes
        $atual = $this->semanal($inicio->copy()->subWeek(), $fim, $f['tipo']);
        $anoAnterior = $comparar
            ? $this->semanal($inicio->copy()->subWeeks(52), $fim->copy()->subWeeks(52), $f['tipo'])
            : collect();

        $zero = $this->semanaZerada();
        $semanas = [];

        for ($semana = $inicio->copy(); $semana <= $fim; $semana->addWeek()) {
            $chave = $semana->format('Y-m-d');
            $linha = (array) ($atual->get($chave) ?? $zero);
            $anterior = (array) ($atual->get($semana->copy()->subWeek()->format('Y-m-d')) ?? $zero);

            $linha['chave'] = $chave;
            // Só o período (segunda a domingo), sem o número da semana ISO: "Sem 10/08 a 16/08"
            $linha['rotulo'] = 'Sem ' . $this->periodo($semana);
            $linha['rotulo_curto'] = $semana->format('d/m');
            $linha['rotulo_longo'] = 'Semana ' . $this->periodo($semana);
            $linha['parcial'] = $chave === $semanaAtual;

            $linha['variacao_valor'] = $linha['valor_liquido'] - $anterior['valor_liquido'];
            $linha['variacao_percentual'] = $anterior['valor_liquido'] > 0
                ? $linha['variacao_valor'] / $anterior['valor_liquido'] * 100 : null;

            if ($comparar) {
                $passada = $semana->copy()->subWeeks(52);
                $ano = (array) ($anoAnterior->get($passada->format('Y-m-d')) ?? $zero);
                $linha['ano_anterior'] = $ano;
                $linha['ano_anterior_rotulo'] = 'Sem ' . $this->periodo($passada) . '/' . $passada->format('Y');
                $linha['variacao_ano_percentual'] = $ano['valor_liquido'] > 0
                    ? ($linha['valor_liquido'] - $ano['valor_liquido']) / $ano['valor_liquido'] * 100 : null;
            }

            $semanas[] = $linha;
        }

        $totais = $this->totais($semanas);

        $totalLiquido = $totais->valor_liquido;
        foreach ($semanas as &$linha) {
            $linha['participacao'] = $totalLiquido > 0 ? $linha['valor_liquido'] / $totalLiquido * 100 : 0;
        }
        unset($linha);

        return [
            'semanas' => $semanas,
            'totais' => $totais,
            'grafico' => $this->grafico($semanas, $f['metrica'], $comparar),
        ];
    }

    /** "19/10 a 25/10" (segunda a domingo da semana que começa em $segunda). */
    private function periodo(Carbon $segunda): string
    {
        return $segunda->format('d/m') . ' a ' . $segunda->copy()->addDays(6)->format('d/m');
    }

    private function semanaZerada(): object
    {
        return (object) [
            'documentos' => 0, 'itens' => 0, 'valor_bruto' => 0.0, 'desconto' => 0.0, 'acrescimo' => 0.0,
            'valor_liquido' => 0.0, 'liquido_nfce' => 0.0, 'liquido_nfe' => 0.0, 'ticket_medio' => 0.0,
        ];
    }

    /** Totais de cada semana entre $de e $ate (segundas, inclusive), indexados pela data da segunda ("AAAA-MM-DD"). */
    private function semanal(Carbon $de, Carbon $ate, string $tipo): Collection
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

        $inicio = $de->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d H:i:s');
        $limite = $ate->copy()->startOfWeek(Carbon::MONDAY)->addDays(6)->endOfDay()->format('Y-m-d H:i:s');

        foreach ([$nfce, $nfe] as $q) {
            $q->where('d.emitida_em', '>=', $inicio)->where('d.emitida_em', '<=', $limite);
        }

        $lados = match ($tipo) {
            'nfce' => $nfce,
            'nfe' => $nfe,
            default => $nfce->unionAll($nfe),
        };

        // WEEKDAY(): segunda = 0. Subtrair isso do dia dá a segunda-feira da semana de cada venda.
        return DB::query()->fromSub($lados, 'r')->selectRaw("
            DATE_FORMAT(DATE_SUB(DATE(r.data), INTERVAL WEEKDAY(r.data) DAY), '%Y-%m-%d') as semana,
            COUNT(DISTINCT r.tipo, r.documento_id) as documentos,
            COUNT(*) as itens,
            COALESCE(SUM(r.valor_bruto), 0) as valor_bruto,
            COALESCE(SUM(r.desconto), 0) as desconto,
            COALESCE(SUM(r.acrescimo), 0) as acrescimo,
            COALESCE(SUM(r.valor_liquido), 0) as valor_liquido,
            COALESCE(SUM(CASE WHEN r.tipo = 'NFC-e' THEN r.valor_liquido END), 0) as liquido_nfce,
            COALESCE(SUM(CASE WHEN r.tipo = 'NF-e' THEN r.valor_liquido END), 0) as liquido_nfe
        ")->groupBy('semana')->get()->map(function ($l) {
            $l->documentos = (int) $l->documentos;
            $l->itens = (int) $l->itens;
            foreach (['valor_bruto', 'desconto', 'acrescimo', 'valor_liquido', 'liquido_nfce', 'liquido_nfe'] as $c) {
                $l->{$c} = (float) $l->{$c};
            }
            $l->ticket_medio = $l->documentos > 0 ? $l->valor_liquido / $l->documentos : 0.0;

            return $l;
        })->keyBy('semana');
    }

    private function totais(array $semanas): object
    {
        $c = collect($semanas);

        $t = (object) [
            'semanas' => count($semanas),
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
        $t->media_semanal = $t->semanas > 0 ? $t->valor_liquido / $t->semanas : 0.0;

        $melhor = $c->sortByDesc('valor_liquido')->first();
        $t->melhor_semana = ($melhor && $melhor['valor_liquido'] > 0) ? $melhor['rotulo'] : null;
        $t->melhor_valor = $melhor['valor_liquido'] ?? 0.0;

        $ultima = $c->last();
        $t->ultimo_rotulo = $ultima['rotulo'] ?? null;
        $t->ultimo_variacao = $ultima['variacao_percentual'] ?? null;
        $t->ultimo_parcial = (bool) ($ultima['parcial'] ?? false);

        // Total das mesmas semanas do ano anterior (só quando a comparação está ligada)
        $t->ano_anterior = $c->contains(fn ($s) => isset($s['ano_anterior']))
            ? (float) $c->sum(fn ($s) => $s['ano_anterior']['valor_liquido'] ?? 0)
            : null;
        $t->variacao_ano = ($t->ano_anterior !== null && $t->ano_anterior > 0)
            ? ($t->valor_liquido - $t->ano_anterior) / $t->ano_anterior * 100 : null;

        return $t;
    }

    /** Valor da semana na métrica escolhida (a mesma do gráfico). */
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
    private function grafico(array $semanas, string $metrica, bool $comparar): array
    {
        $n = count($semanas);
        $ehMoeda = $metrica !== 'documentos';

        $altura = 340;
        $topo = 28;
        $base = 56;
        $x0 = 78;
        $areaAltura = $altura - $topo - $base;
        $yBase = $topo + $areaAltura;
        $slot = $comparar ? 70 : 50;
        $larguraBarra = $comparar ? 25 : 32;
        $largura = max(680, $x0 + $n * $slot + 16);

        $vazia = ['valor_bruto' => 0, 'documentos' => 0, 'ticket_medio' => 0, 'valor_liquido' => 0];

        $valores = [];
        foreach ($semanas as $i => $s) {
            $valores[$i] = $this->valorDaMetrica($s, $metrica);
            if ($comparar) {
                $valores[$i . 'a'] = $this->valorDaMetrica($s['ano_anterior'] ?? $vazia, $metrica);
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

        $mostrarValores = $n <= ($comparar ? 8 : 14);
        $maiorValor = max(array_map(fn ($i) => $valores[$i], array_keys($semanas)) ?: [0]);

        $barras = [];
        foreach ($semanas as $i => $s) {
            $centro = $x0 + $i * $slot + $slot / 2;
            $v = $valores[$i];
            $h = $maximo > 0 ? $v / $maximo * $areaAltura : 0;

            $barra = [
                'rotulo' => $s['rotulo_curto'],
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
                'parcial' => $s['parcial'],
                'tooltip' => $s['rotulo_longo'] . ': ' . $this->valorCompleto($v, $ehMoeda) . ($s['parcial'] ? ' (semana em andamento)' : ''),
            ];

            if ($comparar) {
                $va = $valores[$i . 'a'];
                $ha = $maximo > 0 ? $va / $maximo * $areaAltura : 0;
                $barra['anterior'] = [
                    'x' => round($centro - $larguraBarra - 1, 2),
                    'y' => round($yBase - $ha, 2),
                    'w' => $larguraBarra,
                    'h' => round($ha, 2),
                    'tooltip' => 'Mesma semana do ano anterior, ' . ($s['ano_anterior_rotulo'] ?? '') . ': ' . $this->valorCompleto($va, $ehMoeda),
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
            'legenda_melhor' => 'Melhor semana',
            'legenda_parcial' => 'Semana em andamento',
            'legenda_anterior' => 'Mesma semana do ano anterior',
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
            'Período: ' . $dia($f['data_inicio']) . ' a ' . $dia($f['data_fim']) . ' (semanas inteiras, de segunda a domingo)',
            'Documentos: ' . ['todos' => 'NFC-e e NF-e', 'nfce' => 'Só NFC-e', 'nfe' => 'Só NF-e'][$f['tipo']],
            'Gráfico: ' . self::METRICAS[$f['metrica']],
            'Comparação com o ano anterior: ' . ($f['comparar'] === 'sim' ? 'sim' : 'não'),
        ];
    }
}