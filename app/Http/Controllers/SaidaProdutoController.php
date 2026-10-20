<?php

namespace App\Http\Controllers;

use App\Exports\SaidaProdutoExport;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\MovimentacaoEstoque;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Histórico detalhado das movimentações de estoque, com foco nas saídas.
 * Lê o livro movimentacoes_estoque (gravado pelo EstoqueService).
 */
class SaidaProdutoController extends Controller
{
    private const TIPOS_BUSCA = ['nome', 'codigo_interno', 'codigo_barras', 'referencia'];

    private const SENTIDOS = [
        'saidas'   => 'Saídas',
        'entradas' => 'Entradas',
        'todos'    => 'Entradas e saídas',
    ];

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base);
        $itens = $this->listagem($base, $filtros['ordenar'])
            ->paginate(20)
            ->appends($request->query());

        if ($request->ajax()) {
            return view('produtos.saidas._tabela', compact('itens', 'totais', 'filtros'))->render();
        }

        $categorias = Categoria::orderBy('nome')->get();
        $marcas = Marca::orderBy('nome')->get();
        $operadores = DB::table('users')
            ->whereIn('id', DB::table('movimentacoes_estoque')->whereNotNull('user_id')->select('user_id')->distinct())
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('produtos.saidas.index', compact('itens', 'totais', 'filtros', 'categorias', 'marcas', 'operadores'));
    }

    /** Exporta TODAS as movimentações do filtro atual (não só a página da tela). */
    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $itens = $this->listagem($this->consultaBase($filtros), $filtros['ordenar'])->get();

        return Excel::download(
            new SaidaProdutoExport($itens),
            'saida-produtos-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportarPdf(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base);
        $itens = $this->listagem($base, $filtros['ordenar'])->get();
        $resumoFiltros = $this->resumoFiltros($filtros);

        return Pdf::loadView('produtos.saidas.pdf', compact('itens', 'totais', 'filtros', 'resumoFiltros'))
            ->setPaper('a4', 'landscape')
            ->download('saida-produtos-' . now()->format('Ymd-His') . '.pdf');
    }

    // ------------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------------

    private function consultaBase(array $f)
    {
        return DB::table('movimentacoes_estoque as mv')
            ->join('produtos as p', 'p.id', '=', 'mv.produto_id')
            ->leftJoin('produto_variantes as pv', 'pv.id', '=', 'mv.produto_variante_id')
            ->leftJoin('users as u', 'u.id', '=', 'mv.user_id')
            ->when($f['sentido'] === 'saidas', fn ($q) => $q->where('mv.quantidade', '<', 0))
            ->when($f['sentido'] === 'entradas', fn ($q) => $q->where('mv.quantidade', '>', 0))
            ->when($f['tipo'] !== '', fn ($q) => $q->where('mv.tipo', $f['tipo']))
            ->when($f['categoria'] !== '', fn ($q) => $q->where('p.categoria_id', (int) $f['categoria']))
            ->when($f['marca'] !== '', fn ($q) => $q->where('p.marca_id', (int) $f['marca']))
            ->when($f['operador'] !== '', fn ($q) => $q->where('mv.user_id', (int) $f['operador']))
            ->when($f['busca'] !== '', function ($q) use ($f) {
                $like = '%' . $f['busca'] . '%';

                match ($f['tipo_busca']) {
                    'codigo_interno' => $q->where('p.codigo_interno', 'like', $like),
                    'codigo_barras'  => $q->whereRaw('COALESCE(pv.codigo_barras, p.codigo_barras) LIKE ?', [$like]),
                    'referencia'     => $q->where('p.referencia', 'like', $like),
                    default          => $q->where('p.nome', 'like', $like),
                };
            })
            ->when($f['data_inicio'] !== '', fn ($q) => $q->where('mv.created_at', '>=', $f['data_inicio'] . ' 00:00:00'))
            ->when($f['data_fim'] !== '', fn ($q) => $q->where('mv.created_at', '<=', $f['data_fim'] . ' 23:59:59'));
    }

    private function listagem($base, string $ordenar)
    {
        $q = $base->clone()->select([
            'mv.id',
            'mv.created_at',
            'mv.tipo',
            'mv.quantidade',
            'mv.saldo_anterior',
            'mv.saldo_posterior',
            'mv.custo_unitario',
            'mv.variacao',
            'mv.documento_tipo',
            'mv.documento_id',
            'mv.documento_rotulo',
            'mv.observacao',
            'mv.produto_id',
            'p.nome as descricao',
            'p.codigo_interno',
            DB::raw('COALESCE(pv.codigo_barras, p.codigo_barras) as codigo_barras'),
            'p.unidade_comercial as unidade',
            'u.name as operador',
        ]);

        return match ($ordenar) {
            'antigos' => $q->orderBy('mv.created_at')->orderBy('mv.id'),
            'nome'    => $q->orderBy('p.nome')->orderByDesc('mv.created_at')->orderByDesc('mv.id'),
            default   => $q->orderByDesc('mv.created_at')->orderByDesc('mv.id'),
        };
    }

    private function totais($base): object
    {
        return $base->clone()->selectRaw('
            COUNT(*) AS movimentacoes,
            COALESCE(SUM(CASE WHEN mv.quantidade < 0 THEN -mv.quantidade ELSE 0 END), 0) AS qtd_saidas,
            COALESCE(SUM(CASE WHEN mv.quantidade > 0 THEN mv.quantidade ELSE 0 END), 0) AS qtd_entradas,
            COALESCE(SUM(CASE WHEN mv.quantidade < 0 THEN -mv.quantidade * COALESCE(mv.custo_unitario, 0) ELSE 0 END), 0) AS custo_saidas
        ')->first();
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

        // Sem o parâmetro na URL (primeiro acesso): últimos 30 dias.
        // Com o parâmetro vazio (operador limpou o campo): sem limite.
        $inicio = $request->has('data_inicio')
            ? $this->data($request->input('data_inicio'))
            : now()->subDays(29)->startOfDay();
        $fim = $request->has('data_fim')
            ? $this->data($request->input('data_fim'))
            : now()->startOfDay();

        if ($inicio && $fim && $inicio->gt($fim)) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        $id = fn (string $campo): string => ($n = (int) $request->input($campo)) > 0 ? (string) $n : '';

        return [
            'tipo_busca'  => $escolha('tipo_busca', self::TIPOS_BUSCA, 'nome'),
            'busca'       => trim(is_string($request->input('busca')) ? $request->input('busca') : ''),
            'categoria'   => $id('categoria'),
            'marca'       => $id('marca'),
            'operador'    => $id('operador'),
            'sentido'     => $escolha('sentido', array_keys(self::SENTIDOS), 'saidas'),
            'tipo'        => $escolha('tipo', array_keys(MovimentacaoEstoque::TIPOS), ''),
            'data_inicio' => $inicio?->toDateString() ?? '',
            'data_fim'    => $fim?->toDateString() ?? '',
            'ordenar'     => $escolha('ordenar', ['recentes', 'antigos', 'nome'], 'recentes'),
        ];
    }

    private function data(mixed $valor): ?Carbon
    {
        if ($valor instanceof Carbon) {
            return $valor;
        }

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
        $linhas = ['Exibindo: ' . self::SENTIDOS[$f['sentido']]];

        if ($f['tipo'] !== '') {
            $linhas[] = 'Tipo: ' . MovimentacaoEstoque::TIPOS[$f['tipo']];
        }

        if ($f['categoria'] !== '') {
            $linhas[] = 'Categoria: ' . (Categoria::find($f['categoria'])->nome ?? '—');
        }

        if ($f['marca'] !== '') {
            $linhas[] = 'Marca: ' . (Marca::find($f['marca'])->nome ?? '—');
        }

        if ($f['operador'] !== '') {
            $linhas[] = 'Operador: ' . (DB::table('users')->where('id', $f['operador'])->value('name') ?? '—');
        }

        if ($f['busca'] !== '') {
            $rotulos = [
                'nome' => 'Descrição', 'codigo_interno' => 'Código interno',
                'codigo_barras' => 'Código de barras', 'referencia' => 'Referência',
            ];
            $linhas[] = "Busca por {$rotulos[$f['tipo_busca']]}: {$f['busca']}";
        }

        if ($f['data_inicio'] !== '' || $f['data_fim'] !== '') {
            $de = $f['data_inicio'] !== '' ? Carbon::parse($f['data_inicio'])->format('d/m/Y') : 'início';
            $ate = $f['data_fim'] !== '' ? Carbon::parse($f['data_fim'])->format('d/m/Y') : 'hoje';
            $linhas[] = "Período: de {$de} até {$ate}";
        }

        return $linhas;
    }
}
