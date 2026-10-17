<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos de venda no CAIXA: lê só o espelho do SQLite local (funciona com o servidor fora do ar).
 * O espelho é preenchido pelo SyncService::puxarPedidosVenda().
 */
class PedidoCaixaController extends Controller
{
    /** Lista para o modal "Puxar pedido" do PDV. */
    public function listar(Request $request): JsonResponse
    {
        $busca = trim((string) $request->query('busca'));

        $pedidos = DB::connection('sqlite_local')->table('pedidos_venda_cache as p')
            ->select('p.id', 'p.numero', 'p.data_pedido', 'p.cliente_nome', 'p.operador_nome', 'p.valor_total', 'p.descricao')
            ->selectRaw('(select count(*) from pedido_venda_itens_cache i where i.pedido_venda_id = p.id) as itens_count')
            // pedido que já virou venda neste caixa e ainda não subiu ao servidor não pode ser usado de novo
            ->whereNotIn('p.id', fn ($q) => $q->select('pedido_venda_id')
                ->from('vendas_pendentes')
                ->whereNotNull('pedido_venda_id')
                ->where('status_fiscal', '!=', 'cancelada'))
            ->when($busca !== '', function ($q) use ($busca) {
                $digitos = preg_replace('/\D/', '', $busca);

                $q->where(function ($w) use ($busca, $digitos) {
                    if ($digitos !== '') {
                        $w->orWhere('p.numero', (int) $digitos);
                    }
                    $w->orWhere('p.cliente_nome', 'like', '%' . $busca . '%');
                });
            })
            ->orderByDesc('p.numero')
            ->limit(50)
            ->get();

        return response()->json($pedidos);
    }

    /** Itens do pedido já no formato do carrinho do PDV. */
    public function itens(int $id): JsonResponse
    {
        $db = DB::connection('sqlite_local');

        $pedido = $db->table('pedidos_venda_cache')->where('id', $id)->first();

        if (!$pedido) {
            return response()->json(['message' => 'Pedido não encontrado. Use "Atualizar Caixa" e tente de novo.'], 404);
        }

        $jaUsado = $db->table('vendas_pendentes')
            ->where('pedido_venda_id', $id)
            ->where('status_fiscal', '!=', 'cancelada')
            ->exists();

        if ($jaUsado) {
            return response()->json(['message' => 'Este pedido já foi emitido neste caixa.'], 409);
        }

        $itens = $db->table('pedido_venda_itens_cache')->where('pedido_venda_id', $id)->orderBy('id')->get();

        $produtos = $db->table('produtos_cache')
            ->whereIn('id', $itens->pluck('produto_id')->unique())
            ->get()->keyBy('id');

        $variantes = $db->table('produto_variantes_cache')
            ->whereIn('id', $itens->pluck('produto_variante_id')->filter()->unique())
            ->get()->keyBy('id');

        // A emissão precisa dos dados fiscais do produto no cache do caixa
        $problemas = [];
        foreach ($itens as $i) {
            $p = $produtos->get($i->produto_id);
            $quantidade = (float) $i->quantidade;

            if (!$p || !$p->ativo) {
                $problemas[] = "• {$i->descricao}: produto inativo ou ausente no caixa";
            } elseif ($i->produto_variante_id && !$variantes->has($i->produto_variante_id)) {
                $problemas[] = "• {$i->descricao}: variação ausente no caixa";
            } elseif (!$p->produto_balanca && floor($quantidade) != $quantidade) {
                $problemas[] = "• {$i->descricao}: quantidade fracionada não é aceita para este produto";
            }
        }

        if ($problemas) {
            return response()->json([
                'message' => "Não é possível emitir este pedido:\n" . implode("\n", $problemas)
                    . "\n\nUse \"Atualizar Caixa\" ou corrija o pedido no servidor.",
            ], 422);
        }

        $carrinho = [];

        foreach ($itens as $i) {
            $produto = $produtos->get($i->produto_id);
            $quantidade = (float) $i->quantidade;
            $preco = (float) $i->valor_unitario;
            $chave = $i->produto_id . '-' . ($i->produto_variante_id ?? '0');

            // Produto de balança: o PDV guarda 1 linha com o preço total e o peso à parte
            if ($produto->produto_balanca) {
                $carrinho[] = [
                    'chave' => $chave . '-balanca-' . $i->id,
                    'produto_id' => $i->produto_id,
                    'produto_variante_id' => $i->produto_variante_id,
                    'nome' => $i->descricao . ' (' . rtrim(rtrim(number_format($quantidade, 3, ',', ''), '0'), ',') . ' KG)',
                    'preco' => round($preco * $quantidade, 2),
                    'quantidade' => 1,
                    'eh_balanca' => true,
                    'peso_kg' => $quantidade,
                    'desconto' => 0,
                ];
                continue;
            }

            // Mesmo produto/variação repetido no pedido: soma, como o PDV faz ao bipar duas vezes
            $existente = collect($carrinho)->search(fn ($c) => $c['chave'] === $chave);

            if ($existente !== false) {
                $carrinho[$existente]['quantidade'] += $quantidade;
                continue;
            }

            $carrinho[] = [
                'chave' => $chave,
                'produto_id' => $i->produto_id,
                'produto_variante_id' => $i->produto_variante_id,
                'nome' => $i->descricao,
                'preco' => $preco,
                'quantidade' => $quantidade,
                'desconto' => 0,
            ];
        }

        return response()->json([
            'pedido' => ['id' => $pedido->id, 'numero' => $pedido->numero],
            'itens' => $carrinho,
        ]);
    }
}
