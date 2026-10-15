<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Fornecedor;
use App\Models\PedidoCompra;
use App\Models\Produto;
use App\Models\ProdutoVariante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PedidoCompraController extends Controller
{
    public function index(Request $request)
    {
        $pedidos = PedidoCompra::with('fornecedor')
            ->withCount('itens')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('busca'), fn ($q) => $q->where('numero', (int) preg_replace('/\D/', '', $request->busca)))
            ->when($request->filled('data_de'), fn ($q) => $q->whereDate('data_pedido', '>=', $request->data_de))
            ->when($request->filled('data_ate'), fn ($q) => $q->whereDate('data_pedido', '<=', $request->data_ate))
            ->orderByDesc('numero')
            ->paginate(20)
            ->withQueryString();

        return view('financeiro.pedidos-compra.index', ['pedidos' => $pedidos]);
    }

    public function create()
    {
        return view('financeiro.pedidos-compra.create', [
            'pedido'  => new PedidoCompra(['data_pedido' => now()]),
            'empresa' => Empresa::atual(),
        ]);
    }

    public function store(Request $request)
    {
        $dados = $this->validar($request);

        $pedido = DB::transaction(function () use ($dados) {
            $pedido = PedidoCompra::create([
                'numero'           => PedidoCompra::proximoNumero(),
                'data_pedido'      => $dados['data_pedido'],
                'previsao_entrega' => $dados['previsao_entrega'] ?? null,
                'fornecedor_id'    => $dados['fornecedor_id'],
                'observacao'       => $dados['observacao'] ?? null,
                'status'           => 'aberto',
            ]);

            $pedido->itens()->createMany($dados['itens']);

            return $pedido;
        });

        return redirect()->route('pedidos-compra.index')
            ->with('sucesso', "Pedido de compra {$pedido->numeroExibicao()} criado com sucesso.");
    }

    public function edit(PedidoCompra $pedido)
    {
        if (! $pedido->podeEditar()) {
            return redirect()->route('pedidos-compra.index')
                ->with('erro', 'Somente pedidos em aberto podem ser editados.');
        }

        $pedido->load('itens', 'fornecedor');

        return view('financeiro.pedidos-compra.edit', [
            'pedido'  => $pedido,
            'empresa' => Empresa::atual(),
        ]);
    }

    public function update(Request $request, PedidoCompra $pedido)
    {
        if (! $pedido->podeEditar()) {
            return redirect()->route('pedidos-compra.index')
                ->with('erro', 'Somente pedidos em aberto podem ser editados.');
        }

        $dados = $this->validar($request);

        DB::transaction(function () use ($pedido, $dados) {
            $pedido->update([
                'data_pedido'      => $dados['data_pedido'],
                'previsao_entrega' => $dados['previsao_entrega'] ?? null,
                'fornecedor_id'    => $dados['fornecedor_id'],
                'observacao'       => $dados['observacao'] ?? null,
            ]);

            // pedido em aberto: troca a lista de itens inteira
            $pedido->itens()->delete();
            $pedido->itens()->createMany($dados['itens']);
        });

        return redirect()->route('pedidos-compra.index')
            ->with('sucesso', "Pedido de compra {$pedido->numeroExibicao()} atualizado.");
    }

    public function imprimir(PedidoCompra $pedido)
    {
        $pedido->load('itens', 'fornecedor');

        return view('financeiro.pedidos-compra.imprimir', [
            'pedido'  => $pedido,
            'empresa' => Empresa::atual(),
        ]);
    }

    public function enviar(PedidoCompra $pedido)
    {
        if (! $pedido->podeEnviar()) {
            return back()->with('erro', 'Somente pedidos em aberto podem ser marcados como enviados.');
        }

        $pedido->update(['status' => 'enviado']);

        return back()->with('sucesso', "Pedido {$pedido->numeroExibicao()} marcado como enviado.");
    }

    /**
     * Nunca exclui de verdade — só cancela (mesma regra dos produtos).
     */
    public function cancelar(PedidoCompra $pedido)
    {
        if (! $pedido->podeCancelar()) {
            return back()->with('erro', 'Este pedido não pode mais ser cancelado.');
        }

        $pedido->update(['status' => 'cancelado']);

        return back()->with('sucesso', "Pedido {$pedido->numeroExibicao()} cancelado.");
    }

    /**
     * Busca exata por código de barras (variação ou produto) ou código interno.
     * Usada pelo leitor/digitação na linha "Adicionar item".
     */
    public function buscarProduto(Request $request): JsonResponse
    {
        $codigo = trim((string) $request->query('codigo'));

        if ($codigo === '') {
            return response()->json(['message' => 'Informe o código.'], 422);
        }

        $variante = ProdutoVariante::with('produto')->where('codigo_barras', $codigo)->first();

        if ($variante) {
            $produto = $variante->produto;

            return response()->json([
                'produto_id'          => $produto->id,
                'produto_variante_id' => $variante->id,
                'codigo_barras'       => $variante->codigo_barras,
                'descricao'           => trim($produto->nome . ' - ' . $variante->rotulo(), ' -'),
                'unidade'             => $produto->unidade_comercial ?: 'UN',
            ]);
        }

        $produto = Produto::where('codigo_barras', $codigo)
            ->orWhere('codigo_interno', $codigo)
            ->first();

        if (! $produto) {
            return response()->json(['message' => 'Código não encontrado no cadastro de produtos.'], 404);
        }

        // Produto com variação: o pedido precisa saber qual variação, então exige o código dela
        if ($produto->tem_variacao) {
            return response()->json([
                'message' => "\"{$produto->nome}\" tem variações. Leia ou digite o código de barras da variação desejada.",
            ], 422);
        }

        return response()->json([
            'produto_id'          => $produto->id,
            'produto_variante_id' => null,
            'codigo_barras'       => $produto->codigo_barras,
            'descricao'           => $produto->nome,
            'unidade'             => $produto->unidade_comercial ?: 'UN',
        ]);
    }

    private function validar(Request $request): array
    {
        if (! Empresa::exists()) {
            abort(redirect()->back()->withInput()->withErrors([
                'empresa' => 'Cadastre os dados da empresa antes de emitir pedidos de compra.',
            ]));
        }

        $tabelaFornecedor = (new Fornecedor)->getTable();

        return $request->validate([
            'data_pedido'      => 'required|date',
            'previsao_entrega' => 'nullable|date|after_or_equal:data_pedido',
            'fornecedor_id'    => "required|integer|exists:{$tabelaFornecedor},id",
            'observacao'       => 'nullable|string|max:1000',

            'itens'                       => 'required|array|min:1',
            'itens.*.produto_id'          => 'nullable|integer|exists:produtos,id',
            'itens.*.produto_variante_id' => 'nullable|integer|exists:produto_variantes,id',
            'itens.*.codigo_barras'       => 'nullable|string|max:20',
            'itens.*.descricao'           => 'required|string|max:255',
            'itens.*.unidade'             => 'required|string|max:6',
            'itens.*.quantidade'          => 'required|numeric|min:0.001|max:999999999',
        ], [
            'fornecedor_id.required'     => 'Selecione o fornecedor.',
            'itens.required'             => 'Inclua ao menos um item no pedido.',
            'itens.min'                  => 'Inclua ao menos um item no pedido.',
            'itens.*.descricao.required' => 'Todo item precisa de uma descrição.',
            'itens.*.quantidade.min'     => 'A quantidade de cada item deve ser maior que zero.',
        ]);
    }
}
