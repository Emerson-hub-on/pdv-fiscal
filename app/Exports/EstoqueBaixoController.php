<?php

namespace App\Http\Controllers;

use App\Exports\EstoqueBaixoExport;
use App\Models\Categoria;
use App\Models\Marca;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Relatório de produtos com estoque abaixo do mínimo.
 *
 * Cada linha do relatório é um "item de estoque":
 *  - produto SEM variação  -> uma linha (estoque/mínimo vêm de produtos)
 *  - produto COM variação  -> uma linha por variante (estoque/mínimo vêm de
 *    produto_variantes; o estoque da tabela produtos é ignorado nesse caso)
 *
 * As duas origens são unidas num UNION dentro de uma subquery, assim filtro,
 * ordenação, paginação, totais e exportações usam exatamente a mesma consulta.
 */
class EstoqueBaixoController extends Controller
{
    private const TIPOS_BUSCA = ['nome', 'codigo_interno', 'codigo_barras', 'referencia'];

    private const SITUACOES = [
        'todos'    => 'Todas abaixo do mínimo',
        'abaixo'   => 'Abaixo do mínimo (com estoque)',
        'zerado'   => 'Zerados',
        'negativo' => 'Negativos',
    ];

    private const STATUS = ['ativos' => 'Ativos', 'inativos' => 'Inativos', 'todos' => 'Todos'];

    /** Quantidade que falta para chegar ao mínimo (estoque negativo conta como zero). */
    private const SQL_FALTA = '(estoque_minimo - GREATEST(estoque, 0))';

    private const SQL_SITUACAO = "CASE WHEN estoque < 0 THEN 'negativo' WHEN estoque = 0 THEN 'zerado' ELSE 'abaixo' END";

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base);
        $itens = $this->listagem($base, $filtros['ordenar'])
            ->paginate(20)
            ->appends($request->query());

        if ($request->ajax()) {
            return view('produtos.estoque-baixo._tabela', compact('itens', 'totais', 'filtros'))->render();
        }

        $categorias = Categoria::orderBy('nome')->get();
        $marcas = Marca::orderBy('nome')->get();

        return view('produtos.estoque-baixo.index', compact('itens', 'totais', 'filtros', 'categorias', 'marcas'));
    }

    /** Exporta TODOS os itens do filtro atual (não só a página da tela). */
    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $itens = $this->listagem($this->consultaBase($filtros), $filtros['ordenar'])->get();

        return Excel::download(
            new EstoqueBaixoExport($itens),
            'estoque-baixo-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportarPdf(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base);
        $itens = $this->listagem($base, $filtros['ordenar'])->get();
        $resumoFiltros = $this->resumoFiltros($filtros);

        return Pdf::loadView('produtos.estoque-baixo.pdf', compact('itens', 'totais', 'filtros', 'resumoFiltros'))
            ->setPaper('a4', 'landscape')
            ->download('estoque-baixo-' . now()->format('Ymd-His') . '.pdf');
    }

    // ------------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------------

    /**
     * Base do relatório: já filtrada, sem colunas calculadas nem ordenação.
     */
    private function consultaBase(array $f)
    {
        $produtos = DB::table('produtos as p')
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->leftJoin('marcas as m', 'm.id', '=', 'p.marca_id')
            ->leftJoin('ncms as n', 'n.id', '=', 'p.ncm_id')
            ->where('p.tem_variacao', false)
            ->select([
                'p.id as produto_id',
                DB::raw('NULL as variante_id'),
                'p.codigo_interno',
                'p.codigo_barras',
                'p.nome as descricao',
                DB::raw('NULL as variacao'),
                'p.unidade_comercial as unidade',
                'p.preco_custo as custo',
                'p.preco_venda as venda',
                'p.estoque',
                'p.estoque_minimo',
                'p.referencia',
                'p.ativo',
                'p.categoria_id',
                'p.marca_id',
                'c.nome as categoria',
                'm.nome as marca',
                'n.codigo as ncm',
                'p.created_at as cadastrado_em',
                'p.updated_at as alterado_em',
            ]);

        // Mesma ordem e mesmos nomes de coluna do SELECT acima (exigência do UNION).
        $variantes = DB::table('produto_variantes as v')
            ->join('produtos as p', 'p.id', '=', 'v.produto_id')
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->leftJoin('marcas as m', 'm.id', '=', 'p.marca_id')
            ->leftJoin('ncms as n', 'n.id', '=', 'p.ncm_id')
            ->where('p.tem_variacao', true)
            ->select([
                'p.id as produto_id',
                'v.id as variante_id',
                'p.codigo_interno',
                'v.codigo_barras',
                'p.nome as descricao',
                DB::raw("NULLIF(CONCAT_WS(' / ', NULLIF(v.cor, ''), NULLIF(v.tamanho, '')), '') as variacao"),
                'p.unidade_comercial as unidade',
                'p.preco_custo as custo',
                'p.preco_venda as venda',
                'v.estoque',
                'v.estoque_minimo',
                'p.referencia',
                'p.ativo',
                'p.categoria_id',
                'p.marca_id',
                'c.nome as categoria',
                'm.nome as marca',
                'n.codigo as ncm',
                'v.created_at as cadastrado_em',
                'v.updated_at as alterado_em',
            ]);

        $colunaData = $f['tipo_data'] === 'alteracao' ? 'alterado_em' : 'cadastrado_em';

        return DB::query()
            ->fromSub($produtos->unionAll($variantes), 'itens')
            // Definição de "estoque baixo": tem mínimo configurado e está abaixo dele.
            ->where('estoque_minimo', '>', 0)
            ->whereColumn('estoque', '<', 'estoque_minimo')
            ->when($f['situacao'] === 'abaixo', fn ($q) => $q->where('estoque', '>', 0))
            ->when($f['situacao'] === 'zerado', fn ($q) => $q->where('estoque', '=', 0))
            ->when($f['situacao'] === 'negativo', fn ($q) => $q->where('estoque', '<', 0))
            ->when($f['status'] === 'ativos', fn ($q) => $q->where('ativo', true))
            ->when($f['status'] === 'inativos', fn ($q) => $q->where('ativo', false))
            ->when($f['categoria'] !== '', fn ($q) => $q->where('categoria_id', (int) $f['categoria']))
            ->when($f['marca'] !== '', fn ($q) => $q->where('marca_id', (int) $f['marca']))
            ->when($f['busca'] !== '', function ($q) use ($f) {
                $coluna = $f['tipo_busca'] === 'nome' ? 'descricao' : $f['tipo_busca'];
                $q->where($coluna, 'like', '%' . $f['busca'] . '%');
            })
            ->when($f['data_inicio'] !== '', fn ($q) => $q->where($colunaData, '>=', $f['data_inicio'] . ' 00:00:00'))
            ->when($f['data_fim'] !== '', fn ($q) => $q->where($colunaData, '<=', $f['data_fim'] . ' 23:59:59'));
    }

    /** Acrescenta colunas calculadas e ordenação. */
    private function listagem($base, string $ordenar)
    {
        $q = $base->clone()
            ->select('itens.*')
            ->selectRaw(self::SQL_SITUACAO . ' AS situacao')
            ->selectRaw(self::SQL_FALTA . ' AS falta');

        match ($ordenar) {
            'codigo'    => $q->orderByRaw('CAST(codigo_interno AS UNSIGNED) ASC, codigo_interno ASC'),
            'estoque'   => $q->orderBy('estoque'),
            'reposicao' => $q->orderByDesc('falta'),
            default     => $q->orderBy('descricao'),
        };

        // Desempate estável (paginação não pode embaralhar itens iguais).
        return $q->orderBy('produto_id')->orderBy('variante_id');
    }

    private function totais($base): object
    {
        $falta = self::SQL_FALTA;

        return $base->clone()->selectRaw("
            COUNT(*) AS itens,
            COALESCE(SUM(estoque <= 0), 0) AS zerados,
            COALESCE(SUM({$falta}), 0) AS total_falta,
            COALESCE(SUM({$falta} * COALESCE(custo, 0)), 0) AS custo_reposicao
        ")->first();
    }

    // ------------------------------------------------------------------
    // Filtros
    // ------------------------------------------------------------------

    /** Lê e saneia os filtros; valor inválido volta ao padrão em vez de gerar erro. */
    private function filtros(Request $request): array
    {
        $escolha = function (string $campo, array $validos, string $padrao) use ($request): string {
            $valor = $request->input($campo);

            return is_string($valor) && in_array($valor, $validos, true) ? $valor : $padrao;
        };

        $inicio = $this->data($request->input('data_inicio'));
        $fim = $this->data($request->input('data_fim'));

        if ($inicio && $fim && $inicio->gt($fim)) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        $id = fn (string $campo): string => ($n = (int) $request->input($campo)) > 0 ? (string) $n : '';

        return [
            'tipo_busca'  => $escolha('tipo_busca', self::TIPOS_BUSCA, 'nome'),
            'busca'       => trim((string) (is_string($request->input('busca')) ? $request->input('busca') : '')),
            'categoria'   => $id('categoria'),
            'marca'       => $id('marca'),
            'situacao'    => $escolha('situacao', array_keys(self::SITUACOES), 'todos'),
            'status'      => $escolha('status', array_keys(self::STATUS), 'ativos'),
            'tipo_data'   => $escolha('tipo_data', ['cadastro', 'alteracao'], 'cadastro'),
            'data_inicio' => $inicio?->toDateString() ?? '',
            'data_fim'    => $fim?->toDateString() ?? '',
            'ordenar'     => $escolha('ordenar', ['nome', 'codigo', 'estoque', 'reposicao'], 'nome'),
        ];
    }

    private function data(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $valor)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Linhas de texto com os filtros aplicados (cabeçalho do PDF). */
    private function resumoFiltros(array $f): array
    {
        $linhas = [
            'Situação: ' . self::SITUACOES[$f['situacao']],
            'Status: ' . self::STATUS[$f['status']],
        ];

        if ($f['categoria'] !== '') {
            $linhas[] = 'Categoria: ' . (Categoria::find($f['categoria'])->nome ?? '—');
        }

        if ($f['marca'] !== '') {
            $linhas[] = 'Marca: ' . (Marca::find($f['marca'])->nome ?? '—');
        }

        if ($f['busca'] !== '') {
            $rotulos = [
                'nome' => 'Descrição', 'codigo_interno' => 'Código interno',
                'codigo_barras' => 'Código de barras', 'referencia' => 'Referência',
            ];
            $linhas[] = "Busca por {$rotulos[$f['tipo_busca']]}: {$f['busca']}";
        }

        if ($f['data_inicio'] !== '' || $f['data_fim'] !== '') {
            $rotulo = $f['tipo_data'] === 'alteracao' ? 'Última alteração' : 'Cadastro';
            $de = $f['data_inicio'] !== '' ? Carbon::parse($f['data_inicio'])->format('d/m/Y') : 'início';
            $ate = $f['data_fim'] !== '' ? Carbon::parse($f['data_fim'])->format('d/m/Y') : 'hoje';
            $linhas[] = "{$rotulo}: de {$de} até {$ate}";
        }

        return $linhas;
    }
}
