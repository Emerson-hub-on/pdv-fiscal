<?php

namespace App\Http\Controllers;

use App\Exports\EntradaProdutosExport;
use App\Models\Categoria;
use App\Models\Fornecedor;
use App\Models\Marca;
use App\Models\OperacaoEntrada;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Relatório de entradas de produtos por NF-e.
 *
 * Fonte: itens das notas de entrada (entrada_nota_itens) + cabeçalho (entradas_nota).
 * Duas visões sobre a mesma consulta:
 *  - nota:    uma linha por item de nota (qual NF-e, qual fornecedor, quanto entrou, por quanto)
 *  - produto: uma linha por produto/variação, somando tudo que entrou no período
 *
 * A quantidade recebida é a que vai para o estoque: quantidade da nota (caixas)
 * × qtd_por_caixa, igual ao que EntradaNotaController::finalizar() soma no estoque.
 * O valor movimentado é o valor_total do item (já líquido do desconto do item).
 */
class EntradaProdutosController extends Controller
{
    private const POR_PAGINA = 50;

    private const VISOES = [
        'nota'    => 'Por nota (item a item)',
        'produto' => 'Por produto (consolidado)',
    ];

    private const TIPOS_BUSCA = [
        'produto'        => 'Produto (descrição)',
        'codigo_interno' => 'Código interno',
        'codigo_barras'  => 'Código de barras',
        'nota'           => 'Número da nota',
        'fornecedor'     => 'Fornecedor',
        'chave'          => 'Chave de acesso',
    ];

    private const STATUS = [
        'finalizada' => 'Finalizadas',
        'rascunho'   => 'Rascunhos',
        'cancelada'  => 'Canceladas',
        'todos'      => 'Todos',
    ];

    private const TIPOS_DATA = [
        'entrada' => 'Data de entrada',
        'emissao' => 'Data de emissão',
    ];

    private const ORDENACOES = ['recente', 'antiga', 'nome', 'valor', 'quantidade'];

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base);
        $itens = $this->listagem($base, $filtros)
            ->paginate(self::POR_PAGINA)
            ->appends($request->query());

        if ($request->ajax()) {
            return view('produtos.entradas._tabela', compact('itens', 'totais', 'filtros'))->render();
        }

        $categorias = Categoria::orderBy('nome')->get(['id', 'nome']);
        $marcas = Marca::orderBy('nome')->get(['id', 'nome']);
        $operacoes = OperacaoEntrada::orderBy('descricao')->get(['id', 'descricao']);

        return view('produtos.entradas.index', compact('itens', 'totais', 'filtros', 'categorias', 'marcas', 'operacoes'));
    }

    /** Exporta TODOS os registros do filtro atual (não só a página da tela). */
    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $itens = $this->listagem($this->consultaBase($filtros), $filtros)->get();

        return Excel::download(
            new EntradaProdutosExport($itens, $filtros['visao']),
            'entrada-produtos-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportarPdf(Request $request)
    {
        set_time_limit(120);

        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base);
        $itens = $this->listagem($base, $filtros)->get();
        $resumoFiltros = $this->resumoFiltros($filtros);

        return Pdf::loadView('produtos.entradas.pdf', compact('itens', 'totais', 'filtros', 'resumoFiltros'))
            ->setPaper('a4', 'landscape')
            ->download('entrada-produtos-' . now()->format('Ymd-His') . '.pdf');
    }

    // ------------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------------

    /**
     * Base já filtrada: um registro por item de nota, sem agrupamento nem ordenação.
     * Tudo (tela, totais, Excel, PDF) parte daqui.
     */
    private function consultaBase(array $f)
    {
        // Coluna de data escolhida (lista fixa: nunca vem direto do usuário).
        $colunaData = $f['tipo_data'] === 'emissao' ? 'e.data_emissao' : 'e.data_entrada';
        $tabelaFornecedor = (new Fornecedor())->getTable();

        $itens = DB::table('entrada_nota_itens as i')
            ->join('entradas_nota as e', 'e.id', '=', 'i.entrada_nota_id')
            ->leftJoin("{$tabelaFornecedor} as f", 'f.id', '=', 'e.fornecedor_id')
            ->leftJoin('operacoes_entrada as o', 'o.id', '=', 'e.operacao_entrada_id')
            ->leftJoin('produtos as p', 'p.id', '=', 'i.produto_id')
            ->leftJoin('produto_variantes as v', 'v.id', '=', 'i.produto_variante_id')
            ->selectRaw("
                i.id AS item_id,
                e.id AS entrada_id,
                e.numero,
                e.serie,
                e.modelo,
                e.chave_acesso,
                e.status,
                e.tipo_entrada,
                e.data_entrada,
                e.data_emissao,
                {$colunaData} AS data_ref,
                e.fornecedor_id,
                f.nome AS fornecedor,
                f.nome_fantasia AS fornecedor_fantasia,
                f.cpf_cnpj AS fornecedor_doc,
                e.operacao_entrada_id AS operacao_id,
                o.descricao AS operacao,
                COALESCE(o.movimenta_estoque, 1) AS movimenta_estoque,
                i.produto_id,
                i.produto_variante_id AS variante_id,
                p.codigo_interno,
                CASE WHEN i.produto_variante_id IS NOT NULL THEN v.codigo_barras ELSE p.codigo_barras END AS codigo_barras,
                COALESCE(p.nome, i.descricao) AS descricao,
                NULLIF(CONCAT_WS(' / ', NULLIF(v.cor, ''), NULLIF(v.tamanho, '')), '') AS variacao,
                i.unidade,
                p.unidade_comercial AS unidade_produto,
                p.categoria_id,
                p.marca_id,
                CASE WHEN i.produto_variante_id IS NOT NULL THEN v.estoque ELSE p.estoque END AS estoque_atual,
                i.quantidade,
                GREATEST(COALESCE(i.qtd_por_caixa, 1), 1) AS qtd_por_caixa,
                i.quantidade * GREATEST(COALESCE(i.qtd_por_caixa, 1), 1) AS quantidade_estoque,
                COALESCE(i.valor_unitario, 0) AS valor_unitario,
                COALESCE(i.valor_desconto, 0) AS valor_desconto,
                COALESCE(i.valor_total, 0) AS valor_total
            ");

        return DB::query()
            ->fromSub($itens, 't')
            // Na visão por produto só interessam itens vinculados a um produto cadastrado.
            ->when($f['visao'] === 'produto', fn ($q) => $q->whereNotNull('produto_id'))
            ->when($f['status'] !== 'todos', fn ($q) => $q->where('status', $f['status']))
            ->when($f['operacao'] !== '', fn ($q) => $q->where('operacao_id', (int) $f['operacao']))
            ->when($f['categoria'] !== '', fn ($q) => $q->where('categoria_id', (int) $f['categoria']))
            ->when($f['marca'] !== '', fn ($q) => $q->where('marca_id', (int) $f['marca']))
            ->when($f['busca'] !== '', fn ($q) => $this->aplicarBusca($q, $f))
            ->when($f['data_inicio'] !== '', fn ($q) => $q->where('data_ref', '>=', $f['data_inicio']))
            ->when($f['data_fim'] !== '', fn ($q) => $q->where('data_ref', '<=', $f['data_fim']));
    }

    private function aplicarBusca($q, array $f)
    {
        $termo = '%' . $f['busca'] . '%';

        return match ($f['tipo_busca']) {
            'codigo_interno' => $q->where('codigo_interno', 'like', $termo),
            'codigo_barras'  => $q->where('codigo_barras', 'like', $termo),
            'nota'           => $q->where('numero', 'like', $termo),
            'chave'          => $q->where('chave_acesso', 'like', $termo),
            'fornecedor'     => $q->where(function ($w) use ($f, $termo) {
                $w->where('fornecedor', 'like', $termo)
                    ->orWhere('fornecedor_fantasia', 'like', $termo);

                $digitos = preg_replace('/\D/', '', $f['busca']);
                if ($digitos !== '') {
                    $w->orWhere('fornecedor_doc', 'like', '%' . $digitos . '%');
                }
            }),
            default          => $q->where('descricao', 'like', $termo),
        };
    }

    private function listagem($base, array $f)
    {
        return $f['visao'] === 'produto'
            ? $this->listagemPorProduto($base, $f['ordenar'])
            : $this->listagemPorNota($base, $f['ordenar']);
    }

    private function listagemPorNota($base, string $ordenar)
    {
        $q = $base->clone()
            ->select('t.*')
            ->selectRaw('CASE WHEN quantidade_estoque > 0 THEN valor_total / quantidade_estoque END AS custo_unitario');

        match ($ordenar) {
            'antiga'     => $q->orderBy('data_ref')->orderBy('entrada_id'),
            'nome'       => $q->orderBy('descricao')->orderBy('variacao')->orderByDesc('data_ref'),
            'valor'      => $q->orderByDesc('valor_total'),
            'quantidade' => $q->orderByDesc('quantidade_estoque'),
            default      => $q->orderByDesc('data_ref')->orderByDesc('entrada_id'),
        };

        // Desempate estável: a paginação não pode embaralhar itens iguais.
        return $q->orderBy('item_id');
    }

    private function listagemPorProduto($base, string $ordenar)
    {
        $agrupado = $base->clone()
            ->selectRaw('
                produto_id,
                variante_id,
                codigo_interno,
                codigo_barras,
                descricao,
                variacao,
                unidade_produto AS unidade,
                COUNT(DISTINCT entrada_id) AS notas,
                COUNT(DISTINCT fornecedor_id) AS fornecedores,
                SUM(quantidade_estoque) AS qtd_recebida,
                SUM(valor_total) AS valor_movimentado,
                CASE WHEN SUM(quantidade_estoque) > 0 THEN SUM(valor_total) / SUM(quantidade_estoque) END AS custo_medio,
                MAX(estoque_atual) AS estoque_atual,
                MIN(data_ref) AS primeira_entrada,
                MAX(data_ref) AS ultima_entrada
            ')
            ->groupBy('produto_id', 'variante_id', 'codigo_interno', 'codigo_barras', 'descricao', 'variacao', 'unidade_produto');

        // Subquery: filtro/ordenação/paginação ficam numa consulta simples por cima do GROUP BY.
        $q = DB::query()->fromSub($agrupado, 'g')->select('g.*');

        match ($ordenar) {
            'antiga'     => $q->orderBy('primeira_entrada'),
            'nome'       => $q->orderBy('descricao')->orderBy('variacao'),
            'valor'      => $q->orderByDesc('valor_movimentado'),
            'quantidade' => $q->orderByDesc('qtd_recebida'),
            default      => $q->orderByDesc('ultima_entrada'),
        };

        return $q->orderBy('produto_id')->orderBy('variante_id');
    }

    private function totais($base): object
    {
        return $base->clone()->selectRaw('
            COUNT(DISTINCT entrada_id) AS notas,
            COUNT(DISTINCT produto_id, COALESCE(variante_id, 0)) AS produtos,
            COALESCE(SUM(quantidade_estoque), 0) AS quantidade,
            COALESCE(SUM(valor_desconto), 0) AS desconto,
            COALESCE(SUM(valor_total), 0) AS valor
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

        $inicio = $this->data($request->input('data_inicio'));
        $fim = $this->data($request->input('data_fim'));

        if ($inicio && $fim && $inicio->gt($fim)) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        $id = fn (string $campo): string => ($n = (int) $request->input($campo)) > 0 ? (string) $n : '';

        return [
            'visao'       => $escolha('visao', array_keys(self::VISOES), 'nota'),
            'tipo_busca'  => $escolha('tipo_busca', array_keys(self::TIPOS_BUSCA), 'produto'),
            'busca'       => trim((string) (is_string($request->input('busca')) ? $request->input('busca') : '')),
            'status'      => $escolha('status', array_keys(self::STATUS), 'finalizada'),
            'operacao'    => $id('operacao'),
            'categoria'   => $id('categoria'),
            'marca'       => $id('marca'),
            'tipo_data'   => $escolha('tipo_data', array_keys(self::TIPOS_DATA), 'entrada'),
            'data_inicio' => $inicio?->toDateString() ?? '',
            'data_fim'    => $fim?->toDateString() ?? '',
            'ordenar'     => $escolha('ordenar', self::ORDENACOES, 'recente'),
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
            'Visão: ' . self::VISOES[$f['visao']],
            'Notas: ' . self::STATUS[$f['status']],
        ];

        if ($f['operacao'] !== '') {
            $linhas[] = 'Operação: ' . (OperacaoEntrada::find($f['operacao'])->descricao ?? '—');
        }

        if ($f['categoria'] !== '') {
            $linhas[] = 'Categoria: ' . (Categoria::find($f['categoria'])->nome ?? '—');
        }

        if ($f['marca'] !== '') {
            $linhas[] = 'Marca: ' . (Marca::find($f['marca'])->nome ?? '—');
        }

        if ($f['busca'] !== '') {
            $linhas[] = 'Busca por ' . mb_strtolower(self::TIPOS_BUSCA[$f['tipo_busca']]) . ": {$f['busca']}";
        }

        if ($f['data_inicio'] !== '' || $f['data_fim'] !== '') {
            $rotulo = self::TIPOS_DATA[$f['tipo_data']];
            $de = $f['data_inicio'] !== '' ? Carbon::parse($f['data_inicio'])->format('d/m/Y') : 'início';
            $ate = $f['data_fim'] !== '' ? Carbon::parse($f['data_fim'])->format('d/m/Y') : 'hoje';
            $linhas[] = "{$rotulo}: de {$de} até {$ate}";
        }

        return $linhas;
    }
}
