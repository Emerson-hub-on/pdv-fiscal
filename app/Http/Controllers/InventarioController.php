<?php

namespace App\Http\Controllers;

use App\Exports\InventarioExport;
use App\Models\Categoria;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class InventarioController extends Controller
{
    private const POR_PAGINA = 50;

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base);

        $itens = $this->comCalculos($this->ordenar($base, $filtros['ordenar']))
            ->paginate(self::POR_PAGINA)
            ->appends($request->query());

        if ($request->ajax()) {
            return view('produtos.inventario._tabela', compact('itens', 'totais'))->render();
        }

        $categorias = Categoria::orderBy('nome')->get(['id', 'nome']);

        return view('produtos.inventario.index', compact('itens', 'totais', 'filtros', 'categorias'));
    }

    /**
     * Exporta TODOS os itens do filtro atual (não só a página) em .xlsx.
     */
    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $itens = $this->listaCompleta($filtros);

        return Excel::download(
            new InventarioExport($itens),
            'inventario_' . now()->format('Y-m-d_His') . '.xlsx'
        );
    }

    /**
     * Exporta TODOS os itens do filtro atual em PDF (A4 paisagem).
     */
    public function exportarPdf(Request $request)
    {
        set_time_limit(120);

        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base);
        $itens = $this->comCalculos($this->ordenar($base, $filtros['ordenar']))->get();

        $pdf = Pdf::loadView('produtos.inventario.pdf', [
            'itens' => $itens,
            'totais' => $totais,
            'filtrosAplicados' => $this->descreverFiltros($filtros),
            'geradoEm' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('inventario_' . now()->format('Y-m-d_His') . '.pdf');
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
            'campo_data' => $request->get('campo_data') === 'alteracao' ? 'alteracao' : 'cadastro',
            'data_de' => $this->dataValida($request->get('data_de')),    // Y-m-d ou null
            'data_ate' => $this->dataValida($request->get('data_ate')),  // Y-m-d ou null
        ];
    }

    /** Aceita só datas no formato do input type=date (Y-m-d); qualquer outra coisa vira null. */
    private function dataValida(?string $valor): ?string
    {
        if (! $valor || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }

        return Carbon::hasFormat($valor, 'Y-m-d') ? $valor : null;
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
                p.ativo,
                p.created_at AS criado_em,
                p.updated_at AS atualizado_em
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
                p.ativo,
                p.created_at AS criado_em,
                GREATEST(COALESCE(p.updated_at, v.updated_at), COALESCE(v.updated_at, p.updated_at)) AS atualizado_em
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
            ->when($f['situacao'] === 'com_estoque', fn ($q) => $q->where('estoque', '>', 0))
            ->when($f['data_de'] || $f['data_ate'], function ($q) use ($f) {
                $coluna = $f['campo_data'] === 'alteracao' ? 'atualizado_em' : 'criado_em';

                $q->when($f['data_de'], fn ($q, $de) => $q->where($coluna, '>=', Carbon::parse($de)->startOfDay()))
                    ->when($f['data_ate'], fn ($q, $ate) => $q->where($coluna, '<', Carbon::parse($ate)->addDay()->startOfDay()));
            });

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
     * Colunas calculadas no SQL (tela, Excel e PDF usam a mesma regra):
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

    /**
     * Totais do filtro inteiro (todas as páginas). Estoque negativo não entra
     * na valorização — só quantidade positiva tem valor em inventário.
     */
    private function totais(Builder $base): object
    {
        return (clone $base)->selectRaw(
            'COUNT(*) AS itens,
             COALESCE(SUM(estoque), 0) AS quantidade,
             COALESCE(SUM(GREATEST(estoque, 0) * custo), 0) AS total_custo,
             COALESCE(SUM(GREATEST(estoque, 0) * venda), 0) AS total_venda'
        )->first();
    }

    private function listaCompleta(array $filtros)
    {
        return $this->comCalculos($this->ordenar($this->consultaBase($filtros), $filtros['ordenar']))->get();
    }

    /** Texto dos filtros aplicados, para o cabeçalho do PDF. */
    private function descreverFiltros(array $f): array
    {
        $linhas = [];

        $linhas[] = 'Status: ' . match ($f['status']) {
            'inativos' => 'Inativos',
            'todos' => 'Todos',
            default => 'Ativos',
        };

        if ($f['situacao'] !== 'todos') {
            $linhas[] = 'Situação: ' . match ($f['situacao']) {
                'repor' => 'Abaixo do mínimo',
                'zerado' => 'Zerados',
                'negativo' => 'Negativos',
                'com_estoque' => 'Com estoque',
                default => $f['situacao'],
            };
        }

        if ($f['categoria']) {
            $linhas[] = 'Categoria: ' . (Categoria::find($f['categoria'])?->nome ?? '—');
        }

        if ($f['data_de'] || $f['data_ate']) {
            $campoData = $f['campo_data'] === 'alteracao' ? 'Última alteração' : 'Data de cadastro';
            $de = $f['data_de'] ? Carbon::parse($f['data_de'])->format('d/m/Y') : null;
            $ate = $f['data_ate'] ? Carbon::parse($f['data_ate'])->format('d/m/Y') : null;

            $linhas[] = $campoData . ': ' . match (true) {
                $de && $ate => "de {$de} até {$ate}",
                (bool) $de => "a partir de {$de}",
                default => "até {$ate}",
            };
        }

        if ($f['busca'] !== '') {
            $campo = match ($f['tipo_busca']) {
                'codigo_interno' => 'Código interno',
                'codigo_barras' => 'Código de barras',
                'referencia' => 'Referência',
                default => 'Descrição',
            };
            $linhas[] = $campo . ' contém “' . $f['busca'] . '”';
        }

        return $linhas;
    }
}