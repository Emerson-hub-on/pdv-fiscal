<?php

namespace App\Http\Controllers;

use App\Models\IfoodCliente;
use App\Models\IfoodPedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Comandas → Clientes → Clientes iFood: cadastro, números (pedidos, gasto, ticket, frequência) e histórico de pedidos.
 * Pedido cancelado não conta como pedido nem como gasto (aparece à parte).
 */
class ClienteIfoodController extends Controller
{
    private const ORDENS = [
        'recentes' => 'Último pedido',
        'pedidos'  => 'Mais pedidos',
        'gasto'    => 'Maior gasto',
        'nome'     => 'Nome (A-Z)',
        'antigos'  => 'Clientes mais antigos',
    ];

    public function index(Request $request)
    {
        $busca = mb_substr(trim((string) $request->input('busca', '')), 0, 60);
        $ordenar = array_key_exists($request->input('ordenar'), self::ORDENS) ? $request->input('ordenar') : 'recentes';
        $recorrencia = in_array($request->input('recorrencia'), ['novos', 'recorrentes'], true) ? $request->input('recorrencia') : 'todos';

        $clientes = $this->comNumeros(IfoodCliente::query())
            ->when($busca !== '', function ($q) use ($busca) {
                $digitos = preg_replace('/\D/', '', $busca);
                $q->where(fn ($w) => $w->where('nome', 'like', "%{$busca}%")
                    ->when(strlen($digitos) >= 3, fn ($x) => $x->orWhere('cpf', 'like', "%{$digitos}%"))
                    ->orWhereHas('pedidos', fn ($p) => $p->where('display_id', ltrim($busca, '#'))));
            })
            ->when($recorrencia === 'novos', fn ($q) => $q->having('pedidos_validos', '<=', 1))
            ->when($recorrencia === 'recorrentes', fn ($q) => $q->having('pedidos_validos', '>=', 2));

        match ($ordenar) {
            'pedidos' => $clientes->orderByDesc('pedidos_validos')->orderByDesc('ultimo_pedido_em'),
            'gasto'   => $clientes->orderByDesc('total_gasto')->orderByDesc('ultimo_pedido_em'),
            'nome'    => $clientes->orderBy('nome'),
            'antigos' => $clientes->orderBy('primeiro_pedido_em'),
            default   => $clientes->orderByDesc('ultimo_pedido_em'),
        };

        $clientes = $clientes->paginate(25)->appends($request->query());

        $resumo = $this->resumo();
        $ordens = self::ORDENS;

        return view('comandas.clientes-ifood.index', compact('clientes', 'resumo', 'busca', 'ordenar', 'recorrencia', 'ordens'));
    }

    public function show(IfoodCliente $cliente)
    {
        $cliente = $this->comNumeros(IfoodCliente::whereKey($cliente->id))->firstOrFail();

        $pedidos = $cliente->pedidos()->with('pedido.itens')
            ->orderByDesc(DB::raw('COALESCE(criado_ifood_em, created_at)'))
            ->paginate(20);

        // Produtos mais pedidos (pedidos não cancelados)
        $produtos = DB::table('comanda_pedido_itens as i')
            ->join('ifood_pedidos as f', 'f.comanda_pedido_id', '=', 'i.pedido_id')
            ->where('f.ifood_cliente_id', $cliente->id)
            ->where('f.status', '!=', IfoodPedido::CANCELLED)
            ->groupBy('i.nome')
            ->orderByDesc(DB::raw('SUM(i.quantidade)'))
            ->limit(8)
            ->get(['i.nome', DB::raw('SUM(i.quantidade) as quantidade'), DB::raw('SUM(i.subtotal) as total'), DB::raw('COUNT(DISTINCT f.id) as pedidos')]);

        // Frequência: média de dias entre o primeiro e o último pedido
        $frequencia = $cliente->pedidos_validos >= 2 && $cliente->primeiro_valido && $cliente->ultimo_valido
            ? round(\Carbon\Carbon::parse($cliente->primeiro_valido)->diffInDays(\Carbon\Carbon::parse($cliente->ultimo_valido)) / ($cliente->pedidos_validos - 1), 1)
            : null;

        return view('comandas.clientes-ifood.show', compact('cliente', 'pedidos', 'produtos', 'frequencia'));
    }

    public function salvarObservacao(Request $request, IfoodCliente $cliente): RedirectResponse
    {
        $cliente->update(['observacao' => mb_substr(trim((string) $request->input('observacao', '')), 0, 1000) ?: null]);

        return back()->with('sucesso', 'Anotação do cliente salva.');
    }

    /** Pedidos válidos, cancelados, gasto (concluídos) e datas, calculados dos pedidos. */
    private function comNumeros($query)
    {
        $validos = fn ($q) => $q->where('status', '!=', IfoodPedido::CANCELLED);

        return $query
            ->withCount(['pedidos as pedidos_validos' => $validos])
            ->withCount(['pedidos as pedidos_cancelados' => fn ($q) => $q->where('status', IfoodPedido::CANCELLED)])
            ->withSum(['pedidos as total_gasto' => $validos], 'total')
            ->withMin(['pedidos as primeiro_valido' => $validos], 'criado_ifood_em')
            ->withMax(['pedidos as ultimo_valido' => $validos], 'criado_ifood_em');
    }

    private function resumo(): object
    {
        $pedidos = DB::table('ifood_pedidos')->whereNotNull('ifood_cliente_id')->where('status', '!=', IfoodPedido::CANCELLED);
        $porCliente = (clone $pedidos)->select('ifood_cliente_id', DB::raw('COUNT(*) as n'))->groupBy('ifood_cliente_id');
        $qtdPedidos = (clone $pedidos)->count();

        return (object) [
            'clientes'    => IfoodCliente::count(),
            'novos_mes'   => IfoodCliente::where('primeiro_pedido_em', '>=', now()->startOfMonth())->count(),
            'recorrentes' => DB::query()->fromSub($porCliente, 'x')->where('n', '>=', 2)->count(),
            'ticket'      => $qtdPedidos ? round((float) (clone $pedidos)->sum('total') / $qtdPedidos, 2) : 0,
        ];
    }
}
