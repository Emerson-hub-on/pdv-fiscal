<?php

namespace App\Http\Controllers;

use App\Models\Caixa;
use App\Models\Produto;
use App\Models\ProdutoVariante;
use App\Models\Venda;
use App\Models\VendaItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VendaController extends Controller
{
    public function pdv()
    {
        $caixa = Caixa::aberto(Auth::id());

        if (!$caixa) {
            return redirect()->route('caixa.abrir-form');
        }

        return view('vendas.pdv', compact('caixa'));
    }

    /**
     * Busca produtos por nome, codigo interno ou codigo de barras (usado via AJAX no PDV)
     */
    public function buscarProduto(Request $request)
    {
        $termo = $request->get('termo', '');

        $produtos = Produto::ativos()
            ->where(function ($q) use ($termo) {
                $q->where('nome', 'like', "%{$termo}%")
                  ->orWhere('codigo_interno', 'like', "%{$termo}%")
                  ->orWhere('codigo_barras', 'like', "%{$termo}%");
            })
            ->with('variantes')
            ->limit(10)
            ->get();

        return response()->json($produtos);
    }

    public function finalizar(Request $request)
    {
        $validado = $request->validate([
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|exists:produtos,id',
            'itens.*.produto_variante_id' => 'nullable|exists:produto_variantes,id',
            'itens.*.quantidade' => 'required|integer|min:1',
            'forma_pagamento' => 'required|in:dinheiro,pix,credito,debito',
        ]);

        $caixa = Caixa::aberto(Auth::id());

        if (!$caixa) {
            return response()->json(['erro' => 'Nenhum caixa aberto.'], 422);
        }

        try {
            $venda = DB::transaction(function () use ($validado, $caixa) {
                $total = 0;
                $itensParaSalvar = [];

                foreach ($validado['itens'] as $item) {
                    $produto = Produto::findOrFail($item['produto_id']);

                    // Verifica e baixa estoque com lock, evitando concorrencia entre PDVs
                    if (!empty($item['produto_variante_id'])) {
                        $variante = ProdutoVariante::where('id', $item['produto_variante_id'])
                            ->lockForUpdate()->firstOrFail();

                        if ($variante->estoque < $item['quantidade']) {
                            throw new \Exception("Estoque insuficiente para {$produto->nome} ({$variante->cor}/{$variante->tamanho}).");
                        }

                        $variante->decrement('estoque', $item['quantidade']);
                    } else {
                        $produtoLock = Produto::where('id', $produto->id)->lockForUpdate()->firstOrFail();

                        if ($produtoLock->estoque < $item['quantidade']) {
                            throw new \Exception("Estoque insuficiente para {$produto->nome}.");
                        }

                        $produtoLock->decrement('estoque', $item['quantidade']);
                    }

                    $subtotal = $produto->preco_venda * $item['quantidade'];
                    $total += $subtotal;

                    $itensParaSalvar[] = [
                        'produto_id' => $produto->id,
                        'produto_variante_id' => $item['produto_variante_id'] ?? null,
                        'quantidade' => $item['quantidade'],
                        'preco_unitario' => $produto->preco_venda,
                        'subtotal' => $subtotal,
                    ];
                }

                $venda = Venda::create([
                    'caixa_id' => $caixa->id,
                    'operador_id' => Auth::id(),
                    'total' => $total,
                    'forma_pagamento' => $validado['forma_pagamento'],
                    'status' => 'pendente', // vai virar "emitida" apos a emissao fiscal
                ]);

                foreach ($itensParaSalvar as $itemSalvar) {
                    $venda->itens()->create($itemSalvar);
                }

                return $venda;
            });

            return response()->json([
                'sucesso' => true,
                'venda_id' => $venda->id,
                'total' => $venda->total,
            ]);
        } catch (\Exception $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        }
    }
}