<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventarioController extends Controller
{
    private const POR_PAGINA = 50;

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        // Totais do filtro inteiro (todas as páginas). Estoque negativo não entra
        // na valorização — só quantidade positiva tem valor em inventário.
        $totais = (clone $base)->selectRaw(
            'COUNT(*) AS itens,
             COALESCE(SUM(estoque), 0) AS quantidade,
             COALESCE(SUM(GREATEST(estoque, 0) * custo), 0) AS total_custo,
             COALESCE(SUM(GREATEST(estoque, 0) * venda), 0) AS total_venda'
        )->first();

        $itens = $this->comCalculos($this->ordenar($base, $filtros['ordenar']))
            ->paginate(self::POR_PAGINA)
            ->appends($request->query());

        if ($request->ajax()) {
            return view('produtos.inventario._tabela', compact('itens', 'totais'))->render();
        }

        $categorias = Categoria::orderBy('nome')->get(['id', 'nome']);

        return view('produtos.inventario.index', compact('itens', 'totais', 'filtros', 'categorias'));
    }

    public function exportar(Request $request): StreamedResponse
    {
        $filtros = $this->filtros($request);
        $consulta = $this->comCalculos($this->ordenar($this->consultaBase($filtros), $filtros['ordenar']));

        $situacoes = [
            'ok' => 'OK',
            'abaixo' => 'Abaixo do mínimo',
            'zerado' => 'Zerado',
            'negativo' => 'Negativo',
        ];

        $nomeArquivo = 'inventario_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($consulta, $situacoes) {
            $saida = fopen('php://output', 'w');

            // BOM UTF-8 para o Excel abrir os acentos corretamente
            fwrite($saida, "\xEF\xBB\xBF");

            fputcsv($saida, [
                'Cód. Produto', 'Cód. Barras', 'Unidade', 'Descrição', 'Variação', 'Categoria', 'Marca',
                'NCM', 'Referência', 'Estoque', 'Est. Mínimo', 'Situação', 'Vl. Unitário', 'Vl. Custo',
                'Marg. Lucro %', 'Total Custo', 'Total Venda', 'Status',
            ], ';', '"', '');

            foreach ($consulta->cursor() as $i) {
                $estoquePositivo = max((float) $i->estoque, 0);

                fputcsv($saida, [
                    $i->codigo_interno,
                    // ="..." força o Excel a tratar como texto (evita 7,89E+12 e perda de zeros)
                    $i->codigo_barras ? '="' . $i->codigo_barras . '"' : '',
                    $i->unidade,
                    $i->descricao,
                    $i->variacao,
                    $i->categoria,
                    $i->marca,
                    $i->ncm ? '="' . $i->ncm . '"' : '',
                    $i->referencia,
                    $this->qtd($i->estoque),
                    $this->qtd($i->estoque_minimo),
                    $situacoes[$i->situacao] ?? $i->situacao,
                    $this->dinheiro($i->venda),
                    $this->dinheiro($i->custo),
                    $i->margem !== null ? $this->dinheiro($i->margem) : '',
                    $this->dinheiro($estoquePositivo * (float) $i->custo),
                    $this->dinheiro($estoquePositivo * (float) $i->venda),
                    $i->ativo ? 'Ativo' : 'Inativo',
                ], ';', '"', '');
            }

            fclose($saida);
        }, $nomeArquivo, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtros(Request $request): array
    {
        return [
            'status' => $request->get('status', 'ativos'),        // ativos | inativos | todos
            'situacao' => $request->get('situacao', 'todos'),     // todos | repor | zerado | negativo | com_estoque
            'categoria' => $request->get('categoria'),
            'busca' => trim((string) $request->get('busca', '')),
            'tipo_busca' => $request->get('tipo_busca', 'nome'),  // nome | codigo_interno | codigo_barras | referencia
            'ordenar' => $request->get('ordenar', 'nome'),        // nome | codigo | estoque
        ];
    }

    /**
     * Uma linha por item estocável: produto simples OU cada variação do produto.
     * Produto com variação não aparece como linha própria (seu estoque é a soma
     * das variantes, que já aparecem individualmente).
     * Custo e preço de venda das variações vêm do produto pai.
     */
    private function consultaBase(array $f): Builder
    {
        $simples = DB::table('produtos as p')
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->leftJoin('marcas as m', 'm.id', '=', 'p.marca_id')
            ->leftJoin('ncms as n', 'n.id', '=', 'p.ncm_id')
            ->where('p.tem_variacao', false)
            ->selectRaw("
                p.id AS produto_id,
                NULL AS variante_id,
                p.codigo_interno,
                p.codigo_barras,
                p.unidade_comercial AS unidade,
                p.nome AS descricao,
                NULL AS variacao,
                p.referencia,
                p.categoria_id,
                c.nome AS categoria,
                m.nome AS marca,
                n.codigo AS ncm,
                COALESCE(p.estoque, 0) AS estoque,
                COALESCE(p.estoque_minimo, 0) AS estoque_minimo,
                COALESCE(p.preco_venda, 0) AS venda,
                COALESCE(p.preco_custo, 0) AS custo,
                p.ativo
            ");

        $variantes = DB::table('produto_variantes as v')
            ->join('produtos as p', 'p.id', '=', 'v.produto_id')
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->leftJoin('marcas as m', 'm.id', '=', 'p.marca_id')
            ->leftJoin('ncms as n', 'n.id', '=', 'p.ncm_id')
            ->where('p.tem_variacao', true)
            ->selectRaw("
                p.id AS produto_id,
                v.id AS variante_id,
                p.codigo_interno,
                v.codigo_barras,
                p.unidade_comercial AS unidade,
                p.nome AS descricao,
                NULLIF(CONCAT_WS(' / ', NULLIF(v.cor, ''), NULLIF(v.tamanho, '')), '') AS variacao,
                p.referencia,
                p.categoria_id,
                c.nome AS categoria,
                m.nome AS marca,
                n.codigo AS ncm,
                COALESCE(v.estoque, 0) AS estoque,
                COALESCE(v.estoque_minimo, 0) AS estoque_minimo,
                COALESCE(p.preco_venda, 0) AS venda,
                COALESCE(p.preco_custo, 0) AS custo,
                p.ativo
            ");

        $q = DB::query()->fromSub($simples->unionAll($variantes), 'i');

        $q->when($f['status'] === 'ativos', fn ($q) => $q->where('ativo', true))
            ->when($f['status'] === 'inativos', fn ($q) => $q->where('ativo', false))
            ->when($f['categoria'], fn ($q, $cat) => $q->where('categoria_id', $cat))
            ->when($f['busca'] !== '', function ($q) use ($f) {
                $coluna = match ($f['tipo_busca']) {
                    'codigo_interno' => 'codigo_interno',
                    'codigo_barras' => 'codigo_barras',
                    'referencia' => 'referencia',
                    default => 'descricao',
                };
                $q->where($coluna, 'like', '%' . $f['busca'] . '%');
            })
            ->when($f['situacao'] === 'repor', fn ($q) => $q->whereRaw('estoque_minimo > 0 AND estoque < estoque_minimo'))
            ->when($f['situacao'] === 'zerado', fn ($q) => $q->where('estoque', '=', 0))
            ->when($f['situacao'] === 'negativo', fn ($q) => $q->where('estoque', '<', 0))
            ->when($f['situacao'] === 'com_estoque', fn ($q) => $q->where('estoque', '>', 0));

        return $q;
    }

    private function ordenar(Builder $q, string $ordenarPor): Builder
    {
        return match ($ordenarPor) {
            'codigo' => $q->orderByRaw('CAST(codigo_interno AS UNSIGNED) ASC, codigo_interno ASC, variacao ASC'),
            'estoque' => $q->orderBy('estoque')->orderBy('descricao'),
            default => $q->orderBy('descricao')->orderBy('variacao'),
        };
    }

    /**
     * Colunas calculadas no SQL (tela e CSV usam a mesma regra):
     * - margem: (venda - custo) / venda * 100 — margem sobre o preço de venda,
     *   nula quando falta custo ou preço.
     * - situacao: negativo | zerado | abaixo (abaixo do mínimo) | ok
     */
    private function comCalculos(Builder $q): Builder
    {
        return $q->select('*')->selectRaw("
            CASE WHEN custo > 0 AND venda > 0 THEN ROUND((venda - custo) / venda * 100, 2) END AS margem,
            CASE
                WHEN estoque < 0 THEN 'negativo'
                WHEN estoque = 0 THEN 'zerado'
                WHEN estoque_minimo > 0 AND estoque < estoque_minimo THEN 'abaixo'
                ELSE 'ok'
            END AS situacao
        ");
    }

    private function dinheiro($valor): string
    {
        return number_format((float) $valor, 2, ',', '');
    }

    private function qtd($valor): string
    {
        $valor = (float) $valor;

        return number_format($valor, fmod($valor, 1.0) == 0.0 ? 0 : 3, ',', '');
    }
}
