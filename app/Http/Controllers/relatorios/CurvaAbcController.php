<?php

namespace App\Http\Controllers\Relatorios;

use App\Exports\CurvaAbcExport;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Relatórios > Vendas > Curva ABC
 *
 * Ranking dos produtos por faturamento (valor líquido vendido) no período, com a classificação de Pareto:
 *   A = produtos que formam os primeiros X% do faturamento (padrão 80%)
 *   B = os seguintes, até Y% acumulado (padrão 95%)
 *   C = o restante (menor expressão)
 *
 * A classe é decidida por onde o produto COMEÇA no acumulado: se o acumulado dos produtos anteriores ainda é menor que
 * o limite de A, ele é A (assim o produto que "cruza" os 80% fica em A, e um produto que sozinho passa de 80% não deixa
 * a classe A vazia). A classificação é sempre sobre o período/tipo/vendedor filtrados; a busca e o filtro de classe só
 * escolhem o que mostrar do ranking, sem mudar a classe de ninguém.
 *
 * Base: NFC-e e NF-e de saída emitidas, como nos outros relatórios de vendas. Produtos com variações somam juntos.
 */
class CurvaAbcController extends Controller
{
    // Coluna com o nome do usuário na tabela users (ajuste se for outra, ex.: "nome")
    private const COLUNA_NOME_USUARIO = 'name';

    private const LIMITE_A_PADRAO = 80;
    private const LIMITE_B_PADRAO = 95;
    private const POR_PAGINA = 50;

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $classificado = $this->classificar($filtros);

        $resumo = $this->resumo($classificado);

        $produtos = $this->ordenar($this->lista($classificado, $filtros), $filtros['ordenar'])
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        $dados = compact('produtos', 'resumo', 'filtros');

        if ($request->ajax()) {
            return view('relatorios.vendas.curva-abc._tabela', $dados);
        }

        return view('relatorios.vendas.curva-abc.index', $dados);
    }

    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $classificado = $this->classificar($filtros);

        return Excel::download(
            new CurvaAbcExport(
                $this->ordenar($this->lista($classificado, $filtros), $filtros['ordenar'])->get(),
                $this->resumo($classificado)
            ),
            'curva-abc-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportarPdf(Request $request)
    {
        $filtros = $this->filtros($request);
        $classificado = $this->classificar($filtros);

        $dados = [
            'produtos' => $this->ordenar($this->lista($classificado, $filtros), $filtros['ordenar'])->get(),
            'resumo' => $this->resumo($classificado),
            'filtros' => $filtros,
            'resumoFiltros' => $this->resumoFiltros($filtros),
        ];

        return Pdf::loadView('relatorios.vendas.curva-abc.pdf', $dados)
            ->setPaper('a4')
            ->download('curva-abc-' . now()->format('Ymd-His') . '.pdf');
    }

    private function filtros(Request $request): array
    {
        $primeiraVisita = $request->query() === [];

        $limiteA = (float) $request->get('limite_a', self::LIMITE_A_PADRAO);
        $limiteB = (float) $request->get('limite_b', self::LIMITE_B_PADRAO);

        // Limites inválidos (A fora de 1-99, B menor ou igual a A, B acima de 100) voltam ao padrão
        if ($limiteA < 1 || $limiteA > 99 || $limiteB <= $limiteA || $limiteB > 100) {
            $limiteA = self::LIMITE_A_PADRAO;
            $limiteB = self::LIMITE_B_PADRAO;
        }

        return [
            'busca' => trim((string) $request->get('busca', '')),
            'tipo' => in_array($request->get('tipo'), ['todos', 'nfce', 'nfe'], true)
                ? $request->get('tipo') : 'todos',
            'vendedor' => trim((string) $request->get('vendedor', '')),
            'classe' => in_array($request->get('classe'), ['A', 'B', 'C'], true) ? $request->get('classe') : 'todas',
            // Sem nenhum filtro na URL, abre no mês atual para não varrer o histórico inteiro
            'data_inicio' => $primeiraVisita ? now()->startOfMonth()->toDateString() : (string) $request->get('data_inicio', ''),
            'data_fim' => $primeiraVisita ? now()->toDateString() : (string) $request->get('data_fim', ''),
            'ordenar' => in_array($request->get('ordenar'), ['ranking', 'quantidade', 'produto'], true)
                ? $request->get('ordenar') : 'ranking',
            'limite_a' => $limiteA,
            'limite_b' => $limiteB,
        ];
    }

    /**
     * Itens vendidos (NFC-e + NF-e emitidas) com período, tipo e vendedor aplicados.
     * valor_venda = produtos + acréscimos − descontos (o mesmo "valor líquido" dos outros relatórios).
     * As tabelas têm collations diferentes: os textos levam COLLATE para o UNION não dar "Illegal mix of collations".
     */
    private function itens(array $f): Builder
    {
        $nome = self::COLUNA_NOME_USUARIO;

        $nfce = DB::table('venda_itens as i')
            ->join('vendas as d', 'd.id', '=', 'i.venda_id')
            ->leftJoin('produtos as p', 'p.id', '=', 'i.produto_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.operador_id')
            ->where('d.status', 'emitida')
            ->selectRaw("
                'NFC-e' COLLATE utf8mb4_unicode_ci as tipo,
                d.id as documento_id,
                i.produto_id as produto_id,
                p.codigo_interno COLLATE utf8mb4_unicode_ci as codigo,
                COALESCE(p.nome COLLATE utf8mb4_unicode_ci, i.descricao COLLATE utf8mb4_unicode_ci) as nome,
                i.quantidade as quantidade,
                (COALESCE(i.valor_bruto, i.preco_unitario * i.quantidade) - COALESCE(i.desconto_efetivo, i.desconto, 0)) as valor_venda,
                u.{$nome} COLLATE utf8mb4_unicode_ci as vendedor
            ");

        $nfe = DB::table('nota_fiscal_itens as i')
            ->join('notas_fiscais as d', 'd.id', '=', 'i.nota_fiscal_id')
            ->leftJoin('produtos as p', 'p.id', '=', 'i.produto_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.operador_id')
            ->where('d.status', 'emitida')
            ->where('d.tipo_operacao', '<>', 'entrada')
            ->where('d.finalidade', 1) // só NF-e normal: devolução, complementar e ajuste não são venda
            ->selectRaw("
                'NF-e' COLLATE utf8mb4_unicode_ci as tipo,
                d.id as documento_id,
                i.produto_id as produto_id,
                p.codigo_interno COLLATE utf8mb4_unicode_ci as codigo,
                COALESCE(p.nome COLLATE utf8mb4_unicode_ci, i.descricao COLLATE utf8mb4_unicode_ci) as nome,
                i.quantidade as quantidade,
                ((i.valor_unitario * i.quantidade + i.valor_frete + i.valor_outras_despesas) - COALESCE(i.desconto_efetivo, i.valor_desconto, 0)) as valor_venda,
                u.{$nome} COLLATE utf8mb4_unicode_ci as vendedor
            ");

        foreach ([$nfce, $nfe] as $q) {
            if ($f['data_inicio'] !== '') {
                $q->where('d.emitida_em', '>=', $f['data_inicio'] . ' 00:00:00');
            }
            if ($f['data_fim'] !== '') {
                $q->where('d.emitida_em', '<=', $f['data_fim'] . ' 23:59:59');
            }
        }

        $lados = match ($f['tipo']) {
            'nfce' => $nfce,
            'nfe' => $nfe,
            default => $nfce->unionAll($nfe),
        };

        $itens = DB::query()->fromSub($lados, 't')->select('t.*');

        if ($f['vendedor'] !== '') {
            $itens->where('t.vendedor', 'like', '%' . $f['vendedor'] . '%');
        }

        return $itens;
    }

    /**
     * Um registro por produto, já com posição, % do total, % acumulado e classe (A/B/C).
     * O acumulado é calculado no banco (função de janela), sobre TODOS os produtos do período.
     */
    private function classificar(array $f): Builder
    {
        $agrupado = DB::query()->fromSub($this->itens($f), 'r')->selectRaw('
            r.produto_id,
            MIN(r.codigo) as codigo,
            MIN(r.nome) as nome,
            SUM(r.quantidade) as quantidade,
            COUNT(DISTINCT r.tipo, r.documento_id) as vendas,
            SUM(r.valor_venda) as faturamento
        ')->groupBy('r.produto_id');

        $ranking = DB::query()->fromSub($agrupado, 'a')->selectRaw('
            a.*,
            ROW_NUMBER() OVER (ORDER BY a.faturamento DESC, a.produto_id) as posicao,
            SUM(a.faturamento) OVER (ORDER BY a.faturamento DESC, a.produto_id) as acumulado,
            SUM(a.faturamento) OVER () as total_geral
        ');

        return DB::query()->fromSub($ranking, 'k')->selectRaw("
            k.*,
            CASE WHEN k.total_geral > 0 THEN k.faturamento / k.total_geral * 100 ELSE 0 END as percentual,
            CASE WHEN k.total_geral > 0 THEN LEAST(k.acumulado / k.total_geral * 100, 100) ELSE 100 END as percentual_acumulado,
            CASE
                WHEN k.total_geral <= 0 OR k.faturamento <= 0 THEN 'C'
                WHEN (k.acumulado - k.faturamento) / k.total_geral * 100 < ? THEN 'A'
                WHEN (k.acumulado - k.faturamento) / k.total_geral * 100 < ? THEN 'B'
                ELSE 'C'
            END as classe
        ", [$f['limite_a'], $f['limite_b']]);
    }

    /** O que aparece na tela/exportação: o ranking com o filtro de classe e a busca (que não mudam a classificação). */
    private function lista(Builder $classificado, array $f): Builder
    {
        $q = DB::query()->fromSub(clone $classificado, 'c')->select('c.*');

        if ($f['classe'] !== 'todas') {
            $q->where('c.classe', $f['classe']);
        }

        if ($f['busca'] !== '') {
            $termo = $f['busca'];
            $q->where(fn ($w) => $w
                ->where('c.codigo', 'like', "%{$termo}%")
                ->orWhere('c.nome', 'like', "%{$termo}%"));
        }

        return $q;
    }

    private function ordenar(Builder $q, string $ordem): Builder
    {
        return match ($ordem) {
            'quantidade' => $q->orderByDesc('c.quantidade')->orderBy('c.posicao'),
            'produto' => $q->orderBy('c.nome'),
            default => $q->orderBy('c.posicao'),
        };
    }

    /** Totais por classe (sobre o ranking inteiro, sem o filtro de classe nem a busca) e total geral. */
    private function resumo(Builder $classificado): object
    {
        $porClasse = DB::query()->fromSub(clone $classificado, 'c')
            ->selectRaw('c.classe, COUNT(*) as produtos, SUM(c.faturamento) as faturamento, SUM(c.quantidade) as quantidade')
            ->groupBy('c.classe')
            ->get()
            ->keyBy('classe');

        $totalProdutos = (int) $porClasse->sum('produtos');
        $totalFaturamento = (float) $porClasse->sum('faturamento');

        $classes = [];
        foreach (['A', 'B', 'C'] as $classe) {
            $linha = $porClasse->get($classe);
            $faturamento = (float) ($linha->faturamento ?? 0);
            $produtos = (int) ($linha->produtos ?? 0);

            $classes[$classe] = (object) [
                'produtos' => $produtos,
                'faturamento' => $faturamento,
                'quantidade' => (float) ($linha->quantidade ?? 0),
                'percentual_produtos' => $totalProdutos > 0 ? $produtos / $totalProdutos * 100 : 0,
                'percentual_faturamento' => $totalFaturamento > 0 ? $faturamento / $totalFaturamento * 100 : 0,
            ];
        }

        return (object) [
            'classes' => $classes,
            'produtos' => $totalProdutos,
            'faturamento' => $totalFaturamento,
            'quantidade' => (float) $porClasse->sum('quantidade'),
        ];
    }

    private function resumoFiltros(array $f): array
    {
        $linhas = [];

        $linhas[] = 'Período: ' . ($f['data_inicio'] !== '' ? date('d/m/Y', strtotime($f['data_inicio'])) : 'início')
            . ' a ' . ($f['data_fim'] !== '' ? date('d/m/Y', strtotime($f['data_fim'])) : 'hoje');
        $linhas[] = 'Documentos: ' . ['todos' => 'NFC-e e NF-e', 'nfce' => 'Só NFC-e', 'nfe' => 'Só NF-e'][$f['tipo']];
        $linhas[] = 'Classe A até ' . rtrim(rtrim(number_format($f['limite_a'], 2, ',', ''), '0'), ',') . '% · B até '
            . rtrim(rtrim(number_format($f['limite_b'], 2, ',', ''), '0'), ',') . '%';

        if ($f['classe'] !== 'todas') {
            $linhas[] = 'Só classe ' . $f['classe'];
        }
        if ($f['vendedor'] !== '') {
            $linhas[] = 'Vendedor: ' . $f['vendedor'];
        }
        if ($f['busca'] !== '') {
            $linhas[] = 'Produto: ' . $f['busca'];
        }

        return $linhas;
    }
}
