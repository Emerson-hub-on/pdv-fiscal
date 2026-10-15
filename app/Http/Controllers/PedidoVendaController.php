<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\PedidoVenda;
use App\Models\Produto;
use App\Models\ProdutoVariante;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PedidoVendaController extends Controller
{
    public function index(Request $request)
    {
        $pedidos = PedidoVenda::with('cliente', 'operador')
            ->withCount('itens')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('busca'), fn ($q) => $q->where('numero', (int) preg_replace('/\D/', '', $request->busca)))
            ->when($request->filled('data_de'), fn ($q) => $q->whereDate('data_pedido', '>=', $request->data_de))
            ->when($request->filled('data_ate'), fn ($q) => $q->whereDate('data_pedido', '<=', $request->data_ate))
            ->orderByDesc('numero')
            ->paginate(20)
            ->withQueryString();

        return view('pedidos.venda.index', ['pedidos' => $pedidos]);
    }

    public function create()
    {
        return view('pedidos.venda.create', [
            'pedido' => new PedidoVenda(['data_pedido' => now()]),
        ] + $this->dadosDoFormulario());
    }

    public function store(Request $request)
    {
        $dados = $this->validar($request);

        $pedido = DB::transaction(function () use ($dados) {
            $pedido = PedidoVenda::create([
                'numero'             => PedidoVenda::proximoNumero(),
                'data_pedido'        => $dados['data_pedido'],
                'previsao_entrega'   => $dados['previsao_entrega'] ?? null,
                'operador_id'        => $dados['operador_id'],
                'cliente_id'         => $dados['cliente_id'] ?? null,
                'forma_pagamento_id' => $dados['forma_pagamento_id'] ?? null,
                'descricao'          => $dados['descricao'] ?? null,
                'valor_total'        => $this->totalDosItens($dados['itens']),
                'status'             => 'finalizado',
            ]);

            $pedido->itens()->createMany($dados['itens']);

            return $pedido;
        });

        return redirect()->route('pedidos-venda.index')
            ->with('sucesso', "Pedido de venda {$pedido->numeroExibicao()} finalizado com sucesso.");
    }

    public function edit(PedidoVenda $pedido)
    {
        if (! $pedido->podeEditar()) {
            return redirect()->route('pedidos-venda.index')
                ->with('erro', 'Este pedido não pode mais ser editado.');
        }

        $pedido->load('itens', 'cliente', 'operador');

        return view('pedidos.venda.edit', ['pedido' => $pedido] + $this->dadosDoFormulario());
    }

    public function update(Request $request, PedidoVenda $pedido)
    {
        if (! $pedido->podeEditar()) {
            return redirect()->route('pedidos-venda.index')
                ->with('erro', 'Este pedido não pode mais ser editado.');
        }

        $dados = $this->validar($request, $pedido);

        DB::transaction(function () use ($pedido, $dados) {
            $pedido->update([
                'data_pedido'        => $dados['data_pedido'],
                'previsao_entrega'   => $dados['previsao_entrega'] ?? null,
                'operador_id'        => $dados['operador_id'],
                'cliente_id'         => $dados['cliente_id'] ?? null,
                'forma_pagamento_id' => $dados['forma_pagamento_id'] ?? null,
                'descricao'          => $dados['descricao'] ?? null,
                'valor_total'        => $this->totalDosItens($dados['itens']),
            ]);

            // troca a lista de itens inteira
            $pedido->itens()->delete();
            $pedido->itens()->createMany($dados['itens']);
        });

        return redirect()->route('pedidos-venda.index')
            ->with('sucesso', "Pedido de venda {$pedido->numeroExibicao()} atualizado.");
    }

    public function imprimir(PedidoVenda $pedido)
    {
        $pedido->load('itens', 'cliente', 'operador', 'formaPagamento');

        return view('pedidos.venda.imprimir', [
            'pedido'  => $pedido,
            'empresa' => Empresa::atual(),
        ]);
    }

    /** Cupom no formato de bobina 80 mm (impressora térmica POS-80). */
    public function cupom(PedidoVenda $pedido)
    {
        $pedido->load('itens', 'cliente', 'operador', 'formaPagamento');

        return view('pedidos.venda.cupom', [
            'pedido'  => $pedido,
            'empresa' => Empresa::atual(),
        ]);
    }

    /**
     * Nunca exclui de verdade — só cancela (mesma regra dos produtos).
     */
    public function cancelar(PedidoVenda $pedido)
    {
        if (! $pedido->podeCancelar()) {
            return back()->with('erro', 'Este pedido não pode mais ser cancelado.');
        }

        $pedido->update(['status' => 'cancelado']);

        return back()->with('sucesso', "Pedido {$pedido->numeroExibicao()} cancelado.");
    }

    /**
     * Busca de produtos para o modal da linha "Adicionar item".
     * Aceita nome, código interno, código de barras ou referência.
     * Produto com variação aparece uma linha por variação.
     */
    public function buscarProduto(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('termo'));

        if ($termo === '') {
            return response()->json([]);
        }

        $like = '%' . addcslashes($termo, '\\%_') . '%';

        $simples = Produto::ativos()
            ->where('tem_variacao', false)
            ->where(fn ($q) => $q->where('nome', 'like', $like)
                ->orWhere('codigo_interno', $termo)
                ->orWhere('codigo_barras', $termo)
                ->orWhere('referencia', 'like', $like))
            ->orderBy('nome')
            ->limit(30)
            ->get()
            ->map(fn ($p) => [
                'produto_id'          => $p->id,
                'produto_variante_id' => null,
                'codigo_barras'       => $p->codigo_barras,
                'codigo_interno'      => $p->codigo_interno,
                'descricao'           => $p->nome,
                'unidade'             => $p->unidade_comercial ?: 'UN',
                'preco_venda'         => (float) $p->preco_venda,
                'estoque'             => $p->estoque,
            ]);

        $variacoes = ProdutoVariante::with('produto')
            ->whereHas('produto', fn ($p) => $p->ativos()->where('tem_variacao', true))
            ->where(function ($q) use ($like, $termo) {
                $q->where('codigo_barras', $termo)
                    ->orWhereHas('produto', fn ($p) => $p->where(fn ($w) => $w
                        ->where('nome', 'like', $like)
                        ->orWhere('codigo_interno', $termo)
                        ->orWhere('referencia', 'like', $like)));
            })
            ->limit(30)
            ->get()
            ->map(fn ($v) => [
                'produto_id'          => $v->produto->id,
                'produto_variante_id' => $v->id,
                'codigo_barras'       => $v->codigo_barras,
                'codigo_interno'      => $v->produto->codigo_interno,
                'descricao'           => trim($v->produto->nome . ' - ' . $v->rotulo(), ' -'),
                'unidade'             => $v->produto->unidade_comercial ?: 'UN',
                'preco_venda'         => (float) $v->produto->preco_venda,
                'estoque'             => $v->estoque,
            ]);

        // código exato primeiro, depois ordem alfabética
        $resultado = $simples->concat($variacoes)
            ->sortBy(fn ($r) => [
                ($r['codigo_barras'] === $termo || $r['codigo_interno'] === $termo) ? 0 : 1,
                mb_strtolower($r['descricao']),
            ])
            ->values()
            ->take(30);

        return response()->json($resultado);
    }

    private function dadosDoFormulario(): array
    {
        return [
            // quem pode solicitar pedido: operadores do tipo fiscal (ativos)
            'operadores'       => User::comAcessoAtivo('fiscal')->orderBy('name')->get(['id', 'name', 'codigo']),
            'formasPagamento'  => FormaPagamento::orderBy('descricao')->get(),
            'operadorPadraoId' => auth('web')->id(),
        ];
    }

    private function totalDosItens(array $itens): float
    {
        return round(collect($itens)->sum(
            fn ($i) => round((float) $i['quantidade'] * (float) $i['valor_unitario'], 2)
        ), 2);
    }

    private function validar(Request $request, ?PedidoVenda $atual = null): array
    {
        $tabelaCliente = (new Cliente)->getTable();
        $tabelaForma   = (new FormaPagamento)->getTable();

        return $request->validate([
            'data_pedido'        => 'required|date',
            'previsao_entrega'   => 'nullable|date|after_or_equal:data_pedido',
            'cliente_id'         => "nullable|integer|exists:{$tabelaCliente},id",
            'forma_pagamento_id' => "nullable|integer|exists:{$tabelaForma},id",
            'descricao'          => 'nullable|string|max:1000',

            'operador_id' => ['required', 'integer', function ($attribute, $value, $fail) use ($atual) {
                // ao editar, o operador original continua valendo mesmo que tenha sido desativado depois
                if ($atual && (int) $value === (int) $atual->operador_id) {
                    return;
                }
                if (! User::comAcessoAtivo('fiscal')->whereKey($value)->exists()) {
                    $fail('O operador do pedido deve ser um operador fiscal ativo.');
                }
            }],

            'itens'                       => 'required|array|min:1',
            'itens.*.produto_id'          => 'required|integer|exists:produtos,id',
            'itens.*.produto_variante_id' => 'nullable|integer|exists:produto_variantes,id',
            'itens.*.codigo_barras'       => 'nullable|string|max:20',
            'itens.*.descricao'           => 'required|string|max:255',
            'itens.*.unidade'             => 'required|string|max:6',
            'itens.*.quantidade'          => 'required|numeric|min:0.001|max:999999999',
            'itens.*.valor_unitario'      => 'required|numeric|min:0|max:99999999.99',
        ], [
            'operador_id.required'             => 'Selecione o operador.',
            'itens.required'                   => 'Inclua ao menos um item no pedido.',
            'itens.min'                        => 'Inclua ao menos um item no pedido.',
            'itens.*.quantidade.min'           => 'A quantidade de cada item deve ser maior que zero.',
            'itens.*.valor_unitario.required'  => 'Informe o valor unitário de cada item.',
        ]);
    }
}