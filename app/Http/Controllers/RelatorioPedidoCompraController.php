<?php

namespace App\Http\Controllers;

use App\Exports\RelatorioPedidoCompraExport;
use App\Models\Fornecedor;
use App\Models\PedidoCompra;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Financeiro > Relatório > Pedidos de Compra.
 *
 * Uma linha por pedido; os itens abrem num modal ("Ver itens").
 * Exportação (PDF/Excel): "detalhada" inclui os itens; "resumida" só os pedidos.
 *
 * O pedido de compra não tem valores (só itens e quantidades), então os cards são contagens.
 */
class RelatorioPedidoCompraController extends Controller
{
    private const TIPOS_BUSCA = ['numero', 'fornecedor', 'cnpj', 'produto'];

    private const STATUS = [
        'andamento' => 'Em andamento (abertos e enviados)',
        'aberto'    => 'Abertos',
        'enviado'   => 'Enviados',
        'atrasado'  => 'Atrasados (previsão vencida)',
        'cancelado' => 'Cancelados',
        'todos'     => 'Todos',
    ];

    private const VISOES = ['detalhada' => 'Com itens (detalhada)', 'resumida' => 'Só os pedidos (resumida)'];

    private const TIPOS_DATA = [
        'pedido'   => 'Data do pedido',
        'previsao' => 'Previsão de entrega',
    ];

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base);
        // Na tela os itens vêm junto para abrir no modal sem nova requisição
        $pedidos = $this->listagem($base, $filtros)
            ->paginate(20)
            ->appends($request->query());

        if ($request->ajax()) {
            return view('financeiro.relatorios.pedidos-compra._tabela', compact('pedidos', 'totais', 'filtros'))->render();
        }

        return view('financeiro.relatorios.pedidos-compra.index', compact('pedidos', 'totais', 'filtros'));
    }

    /** Exporta TODOS os pedidos do filtro atual (não só a página da tela). */
    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $pedidos = $this->listagem($this->consultaBase($filtros), $filtros)->get();

        return Excel::download(
            new RelatorioPedidoCompraExport($pedidos, $filtros['visao'] === 'detalhada'),
            'pedidos-compra-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportarPdf(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base);
        $pedidos = $this->listagem($base, $filtros)->get();
        $resumoFiltros = $this->resumoFiltros($filtros);

        return Pdf::loadView('financeiro.relatorios.pedidos-compra.pdf', compact('pedidos', 'totais', 'filtros', 'resumoFiltros'))
            ->setPaper('a4', 'landscape')
            ->download('pedidos-compra-' . now()->format('Ymd-His') . '.pdf');
    }

    // ------------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------------

    private function consultaBase(array $f)
    {
        $colunaData = $f['tipo_data'] === 'previsao' ? 'previsao_entrega' : 'data_pedido';

        return PedidoCompra::query()
            ->when($f['status'] === 'andamento', fn ($q) => $q->whereIn('status', ['aberto', 'enviado']))
            ->when(in_array($f['status'], ['aberto', 'enviado', 'cancelado'], true), fn ($q) => $q->where('status', $f['status']))
            ->when($f['status'] === 'atrasado', fn ($q) => $q
                ->whereIn('status', ['aberto', 'enviado'])
                ->whereDate('previsao_entrega', '<', today()))
            ->when($f['busca'] !== '', fn ($q) => $this->aplicarBusca($q, $f))
            ->when($f['data_inicio'] !== '', fn ($q) => $q->whereDate($colunaData, '>=', $f['data_inicio']))
            ->when($f['data_fim'] !== '', fn ($q) => $q->whereDate($colunaData, '<=', $f['data_fim']));
    }

    private function aplicarBusca($q, array $f): void
    {
        $busca = $f['busca'];
        $digitos = preg_replace('/\D/', '', $busca);

        match ($f['tipo_busca']) {
            'numero'  => $q->where('numero', $digitos !== '' ? (int) $digitos : -1),
            'cnpj'    => $q->whereHas('fornecedor', fn ($c) => $c->where('cpf_cnpj', 'like', '%' . ($digitos !== '' ? $digitos : $busca) . '%')),
            'produto' => $q->whereHas('itens', fn ($i) => $i->where(
                fn ($w) => $w->where('descricao', 'like', "%{$busca}%")
                    ->orWhere('codigo_barras', $busca)
                    ->orWhereHas('produto', fn ($p) => $p->where('codigo_interno', $busca)->orWhere('codigo_barras', $busca))
            )),
            default   => $q->whereHas('fornecedor', fn ($c) => $c->where('nome', 'like', "%{$busca}%")
                ->orWhere('nome_fantasia', 'like', "%{$busca}%")),
        };
    }

    private function listagem($base, array $f)
    {
        $q = (clone $base)->with(['fornecedor', 'itens.produto', 'itens.variante']);

        match ($f['ordenar']) {
            'antigos'    => $q->orderBy('numero'),
            'previsao'   => $q->orderByRaw('previsao_entrega is null')->orderBy('previsao_entrega')->orderByDesc('numero'),
            'fornecedor' => $q->orderBy(
                Fornecedor::select('nome')->whereColumn((new Fornecedor)->getTable() . '.id', 'pedidos_compra.fornecedor_id')->limit(1)
            )->orderByDesc('numero'),
            default      => $q->orderByDesc('numero'),
        };

        return $q;
    }

    private function totais($base): object
    {
        return (clone $base)->toBase()->selectRaw("
            COALESCE(SUM(status = 'aberto'), 0) AS abertos,
            COALESCE(SUM(status = 'enviado'), 0) AS enviados,
            COALESCE(SUM(status = 'cancelado'), 0) AS cancelados,
            COALESCE(SUM(status IN ('aberto', 'enviado') AND previsao_entrega < ?), 0) AS atrasados
        ", [today()->toDateString()])->first();
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

        // Sem período por padrão: pedido em andamento não pode sumir por causa da data.
        $inicio = $this->data($request->input('data_inicio'));
        $fim = $this->data($request->input('data_fim'));

        if ($inicio && $fim && $inicio->gt($fim)) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        return [
            'tipo_busca'  => $escolha('tipo_busca', self::TIPOS_BUSCA, 'numero'),
            'busca'       => trim(is_string($request->input('busca')) ? $request->input('busca') : ''),
            'status'      => $escolha('status', array_keys(self::STATUS), 'andamento'),
            'tipo_data'   => $escolha('tipo_data', array_keys(self::TIPOS_DATA), 'pedido'),
            'data_inicio' => $inicio?->toDateString() ?? '',
            'data_fim'    => $fim?->toDateString() ?? '',
            'visao'       => $escolha('visao', array_keys(self::VISOES), 'detalhada'),
            'ordenar'     => $escolha('ordenar', ['recentes', 'antigos', 'previsao', 'fornecedor'], 'recentes'),
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
            'Pedidos: ' . self::STATUS[$f['status']],
            'Exportação: ' . self::VISOES[$f['visao']],
        ];

        if ($f['busca'] !== '') {
            $rotulos = ['numero' => 'Número', 'fornecedor' => 'Fornecedor', 'cnpj' => 'CPF/CNPJ', 'produto' => 'Produto'];
            $linhas[] = "Busca por {$rotulos[$f['tipo_busca']]}: {$f['busca']}";
        }

        if ($f['data_inicio'] !== '' || $f['data_fim'] !== '') {
            $de = $f['data_inicio'] !== '' ? Carbon::parse($f['data_inicio'])->format('d/m/Y') : 'início';
            $ate = $f['data_fim'] !== '' ? Carbon::parse($f['data_fim'])->format('d/m/Y') : 'sem limite';
            $linhas[] = self::TIPOS_DATA[$f['tipo_data']] . ": de {$de} até {$ate}";
        }

        return $linhas;
    }
}
