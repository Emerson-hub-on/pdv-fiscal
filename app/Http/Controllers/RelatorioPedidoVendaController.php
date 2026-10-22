<?php

namespace App\Http\Controllers;

use App\Exports\RelatorioPedidoVendaExport;
use App\Models\Cliente;
use App\Models\FormaPagamento;
use App\Models\PedidoVenda;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Pedidos > Relatório > Pedido de Venda.
 *
 * Uma linha por pedido; os itens abrem num modal ("Ver itens").
 * Exportação (PDF/Excel): "detalhada" inclui os itens; "resumida" só os pedidos.
 */
class RelatorioPedidoVendaController extends Controller
{
    private const TIPOS_BUSCA = ['numero', 'cliente', 'cnpj', 'produto'];

    // finalizado = pedido fechado; emitido = pedido que já foi emitido no caixa; cancelado = não conta como venda
    private const STATUS = [
        'todos'      => 'Todos',
        'finalizado' => 'Finalizados',
        'emitido'    => 'Emitidos',
        'cancelado'  => 'Cancelados',
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
            return view('pedidos.relatorios.venda._tabela', compact('pedidos', 'totais', 'filtros'))->render();
        }

        $formasPagamento = FormaPagamento::orderBy('descricao')->get(['id', 'descricao']);
        $operadores = DB::table('users')
            ->whereIn('id', DB::table('pedidos_venda')->whereNotNull('operador_id')->select('operador_id')->distinct())
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('pedidos.relatorios.venda.index', compact('pedidos', 'totais', 'filtros', 'formasPagamento', 'operadores'));
    }

    /** Exporta TODOS os pedidos do filtro atual (não só a página da tela). */
    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $pedidos = $this->listagem($this->consultaBase($filtros), $filtros)->get();

        return Excel::download(
            new RelatorioPedidoVendaExport($pedidos, $filtros['visao'] === 'detalhada'),
            'pedidos-venda-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportarPdf(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base);
        $pedidos = $this->listagem($base, $filtros)->get();
        $resumoFiltros = $this->resumoFiltros($filtros);

        return Pdf::loadView('pedidos.relatorios.venda.pdf', compact('pedidos', 'totais', 'filtros', 'resumoFiltros'))
            ->setPaper('a4', 'landscape')
            ->download('pedidos-venda-' . now()->format('Ymd-His') . '.pdf');
    }

    // ------------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------------

    private function consultaBase(array $f)
    {
        $colunaData = $f['tipo_data'] === 'previsao' ? 'previsao_entrega' : 'data_pedido';

        return PedidoVenda::query()
            ->when($f['status'] !== 'todos', fn ($q) => $q->where('status', $f['status']))
            ->when($f['operador'] !== '', fn ($q) => $q->where('operador_id', (int) $f['operador']))
            ->when($f['forma'] !== '', fn ($q) => $q->where('forma_pagamento_id', (int) $f['forma']))
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
            'cnpj'    => $this->temColunaDocumento()
                ? $q->whereHas('cliente', fn ($c) => $c->where('cpf_cnpj', 'like', '%' . ($digitos !== '' ? $digitos : $busca) . '%'))
                : $q->whereRaw('1 = 0'),
            'produto' => $q->whereHas('itens', fn ($i) => $i->where(
                fn ($w) => $w->where('descricao', 'like', "%{$busca}%")
                    ->orWhere('codigo_barras', $busca)
                    ->orWhereHas('produto', fn ($p) => $p->where('codigo_interno', $busca)->orWhere('codigo_barras', $busca))
            )),
            default   => $q->whereHas('cliente', fn ($c) => $c->where($this->colunaNomeCliente(), 'like', "%{$busca}%")),
        };
    }

    private function listagem($base, array $f)
    {
        $q = (clone $base)->with(['cliente', 'operador', 'formaPagamento', 'itens.produto']);

        match ($f['ordenar']) {
            'antigos'  => $q->orderBy('numero'),
            'valor'    => $q->orderByDesc('valor_total')->orderByDesc('numero'),
            'previsao' => $q->orderByRaw('previsao_entrega is null')->orderBy('previsao_entrega')->orderByDesc('numero'),
            'cliente'  => $q->orderBy(
                Cliente::select($this->colunaNomeCliente())->whereColumn((new Cliente)->getTable() . '.id', 'pedidos_venda.cliente_id')->limit(1)
            )->orderByDesc('numero'),
            default    => $q->orderByDesc('numero'),
        };

        return $q;
    }

    /** Cancelados sempre separados: pedido cancelado não entra no valor dos pedidos. */
    private function totais($base): object
    {
        return (clone $base)->toBase()->selectRaw("
            COALESCE(SUM(status = 'finalizado'), 0) AS finalizados,
            COALESCE(SUM(status = 'emitido'), 0) AS emitidos,
            COALESCE(SUM(CASE WHEN status IN ('finalizado', 'emitido') THEN valor_total ELSE 0 END), 0) AS valor_pedidos,
            COALESCE(SUM(status = 'cancelado'), 0) AS cancelados,
            COALESCE(SUM(CASE WHEN status = 'cancelado' THEN valor_total ELSE 0 END), 0) AS valor_cancelado
        ")->first();
    }

    /** Coluna de nome da tabela de clientes (nome, razao_social ou nome_fantasia, a primeira que existir). */
    private function colunaNomeCliente(): string
    {
        static $coluna = null;

        return $coluna ??= collect(['nome', 'razao_social', 'nome_fantasia'])
            ->first(fn ($c) => Schema::hasColumn((new Cliente)->getTable(), $c), 'id');
    }

    private function temColunaDocumento(): bool
    {
        static $tem = null;

        return $tem ??= Schema::hasColumn((new Cliente)->getTable(), 'cpf_cnpj');
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

        // Sem o parâmetro na URL (primeiro acesso): mês atual.
        // Com o parâmetro vazio (operador limpou o campo): sem limite.
        $inicio = $request->has('data_inicio')
            ? $this->data($request->input('data_inicio'))
            : now()->startOfMonth()->startOfDay();
        $fim = $request->has('data_fim')
            ? $this->data($request->input('data_fim'))
            : now()->startOfDay();

        if ($inicio && $fim && $inicio->gt($fim)) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        $id = fn (string $campo): string => ($n = (int) $request->input($campo)) > 0 ? (string) $n : '';

        return [
            'tipo_busca'  => $escolha('tipo_busca', self::TIPOS_BUSCA, 'numero'),
            'busca'       => trim(is_string($request->input('busca')) ? $request->input('busca') : ''),
            'status'      => $escolha('status', array_keys(self::STATUS), 'todos'),
            'operador'    => $id('operador'),
            'forma'       => $id('forma'),
            'tipo_data'   => $escolha('tipo_data', array_keys(self::TIPOS_DATA), 'pedido'),
            'data_inicio' => $inicio?->toDateString() ?? '',
            'data_fim'    => $fim?->toDateString() ?? '',
            'visao'       => $escolha('visao', array_keys(self::VISOES), 'detalhada'),
            'ordenar'     => $escolha('ordenar', ['recentes', 'antigos', 'valor', 'previsao', 'cliente'], 'recentes'),
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

        if ($f['operador'] !== '') {
            $linhas[] = 'Operador: ' . (DB::table('users')->where('id', $f['operador'])->value('name') ?? '—');
        }

        if ($f['forma'] !== '') {
            $linhas[] = 'Forma de pagamento: ' . (FormaPagamento::find($f['forma'])->descricao ?? '—');
        }

        if ($f['busca'] !== '') {
            $rotulos = ['numero' => 'Número', 'cliente' => 'Cliente', 'cnpj' => 'CPF/CNPJ', 'produto' => 'Produto'];
            $linhas[] = "Busca por {$rotulos[$f['tipo_busca']]}: {$f['busca']}";
        }

        if ($f['data_inicio'] !== '' || $f['data_fim'] !== '') {
            $de = $f['data_inicio'] !== '' ? Carbon::parse($f['data_inicio'])->format('d/m/Y') : 'início';
            $ate = $f['data_fim'] !== '' ? Carbon::parse($f['data_fim'])->format('d/m/Y') : 'hoje';
            $linhas[] = self::TIPOS_DATA[$f['tipo_data']] . ": de {$de} até {$ate}";
        }

        return $linhas;
    }
}