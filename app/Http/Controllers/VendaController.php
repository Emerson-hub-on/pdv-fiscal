<?php

namespace App\Http\Controllers;

use App\Models\Caixa;
use App\Services\SyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
     * Busca produtos no banco LOCAL (sqlite), nao mais no MySQL central.
     */
    public function buscarProduto(Request $request)
    {
        $termo = $request->get('termo', '');

        $produtos = DB::connection('sqlite_local')->table('produtos_cache')
            ->where('ativo', true)
            ->where(function ($q) use ($termo) {
                $q->where('nome', 'like', "%{$termo}%")
                  ->orWhere('codigo_interno', 'like', "%{$termo}%")
                  ->orWhere('codigo_barras', 'like', "%{$termo}%");
            })
            ->limit(10)
            ->get();

        // Anexa variantes de cada produto que tem variacao
        $produtos = $produtos->map(function ($produto) {
            $produto->variantes = $produto->tem_variacao
                ? DB::connection('sqlite_local')->table('produto_variantes_cache')
                    ->where('produto_id', $produto->id)
                    ->get()
                : [];
            return $produto;
        });

        return response()->json($produtos);
    }

    /**
     * Grava a venda no banco LOCAL (fila de pendentes), nao mais direto no MySQL.
     */

    public function prepararPagamento(Request $request)
    {
        $validado = $request->validate([
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|integer',
            'itens.*.produto_variante_id' => 'nullable|integer',
            'itens.*.nome' => 'required|string',
            'itens.*.quantidade' => 'required|integer|min:1',
            'itens.*.preco' => 'required|numeric',
            'itens.*.desconto' => 'nullable|numeric',
            'itens.*.subtotal' => 'required|numeric',
            'desconto_item' => 'required|numeric',
            'desconto_global' => 'required|numeric',
            'total' => 'required|numeric',
        ]);

        session(['venda_carrinho' => $validado]);

        return response()->json(['sucesso' => true]);
    }

    public function telaPagamento()
    {
        $dados = session('venda_carrinho');

        if (!$dados) {
            return redirect()->route('vendas.pdv')->with('erro', 'Nenhum item no carrinho. Adicione itens antes de prosseguir.');
        }

        return view('vendas.pagamento', $dados);
    }
    
    public function finalizar(Request $request)
    {
    $validado = $request->validate([
        'itens' => 'required|array|min:1',
        'itens.*.produto_id' => 'required|integer',
        'itens.*.produto_variante_id' => 'nullable|integer',
        'itens.*.quantidade' => 'required|integer|min:1',
        'itens.*.desconto' => 'nullable|numeric|min:0',
        'pagamentos' => 'required|array|min:1',
        'pagamentos.*.forma_pagamento' => 'required|in:dinheiro,pix,credito,debito',
        'pagamentos.*.valor' => 'required|numeric|min:0.01',
    ]);

        $caixa = Caixa::aberto(Auth::id());

        if (!$caixa) {
            return response()->json(['erro' => 'Nenhum caixa aberto.'], 422);
        }

        try {
            $total = 0;
            $itensParaSalvar = [];

            DB::connection('sqlite_local')->transaction(function () use ($validado, &$total, &$itensParaSalvar) {
                foreach ($validado['itens'] as $item) {
                    if (!empty($item['produto_variante_id'])) {
                        $variante = DB::connection('sqlite_local')->table('produto_variantes_cache')
                            ->where('id', $item['produto_variante_id'])->lockForUpdate()->first();

                        if (!$variante || $variante->estoque < $item['quantidade']) {
                            throw new \Exception('Estoque insuficiente (local) para o item selecionado.');
                        }

                        DB::connection('sqlite_local')->table('produto_variantes_cache')
                            ->where('id', $variante->id)
                            ->decrement('estoque', $item['quantidade']);

                        $produto = DB::connection('sqlite_local')->table('produtos_cache')
                            ->where('id', $item['produto_id'])->first();
                    } else {
                        $produto = DB::connection('sqlite_local')->table('produtos_cache')
                            ->where('id', $item['produto_id'])->lockForUpdate()->first();

                        if (!$produto || $produto->estoque < $item['quantidade']) {
                            throw new \Exception('Estoque insuficiente (local) para ' . ($produto->nome ?? 'produto'));
                        }

                        DB::connection('sqlite_local')->table('produtos_cache')
                            ->where('id', $produto->id)
                            ->decrement('estoque', $item['quantidade']);
                    }

                    $desconto = min($item['desconto'] ?? 0, $produto->preco_venda * $item['quantidade']);
                    $subtotal = ($produto->preco_venda * $item['quantidade']) - $desconto;
                    $total += $subtotal;

                    $itensParaSalvar[] = [
                        'produto_id' => $item['produto_id'],
                        'produto_variante_id' => $item['produto_variante_id'] ?? null,
                        'quantidade' => $item['quantidade'],
                        'preco_unitario' => $produto->preco_venda,
                        'desconto' => $desconto,
                    ];
                }
            });
            $descontoTotal = collect($itensParaSalvar)->sum('desconto');

            // Confere se a soma dos pagamentos bate com o total calculado dos itens
            $totalPagamentos = collect($validado['pagamentos'])->sum('valor');
            $troco = round($totalPagamentos - $total, 2);

            if ($troco < -0.01) {
                return response()->json(['erro' => 'A soma dos pagamentos é menor que o total da venda.'], 422);
            }

            $uuid = (string) Str::uuid();

            DB::connection('sqlite_local')->table('vendas_pendentes')->insert([
                'uuid' => $uuid,
                'caixa_id_central' => $caixa->id,
                'operador_id_central' => Auth::id(),
                'total' => $total,
                'troco' => $troco,
                'desconto' => $descontoTotal,
                'forma_pagamento' => null,
                'pagamentos' => json_encode($validado['pagamentos']),
                'itens' => json_encode($itensParaSalvar),
                'status' => 'pendente_sync',
                'vendida_em' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $emissao = ['sucesso' => false, 'contingencia' => false, 'erro' => null];

            try {
                (new SyncService())->enviarVendasPendentes();

                // Ja sincronizou? Tenta emitir automaticamente, sem esperar o operador clicar
                $vendaCentral = \App\Models\Venda::where('uuid', $uuid)->first();

                if ($vendaCentral) {
                    try {
                        $resultado = (new \App\Services\FiscalEmissorService())->emitir($vendaCentral);
                        $emissao = ['sucesso' => true, 'contingencia' => false, 'chave' => $resultado['chave']];
                    } catch (\Exception $e) {
                        $vendaCentral->refresh();
                        $emissao = [
                            'sucesso' => false,
                            'contingencia' => $vendaCentral->status === 'contingencia',
                            'erro' => $e->getMessage(),
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Nem a sincronizacao rolou - venda fica local, o scheduler tenta depois
            }

            return response()->json([
                'sucesso' => true,
                'venda_uuid' => $uuid,
                'total' => $total,
                'emissao' => $emissao,
            ]);
        } catch (\Exception $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        }
    }
}