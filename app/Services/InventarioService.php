<?php

namespace App\Services;

use App\Models\Inventario;
use App\Models\InventarioItem;
use App\Models\MovimentacaoEstoque;
use App\Models\Produto;
use App\Models\ProdutoVariante;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Calcula o estoque de cada produto no fim de uma data a partir do histórico (movimentacoes_estoque).
 *
 * saldo na data = saldo atual − soma das movimentações registradas DEPOIS da data
 *
 * Funciona porque o EstoqueService é o único que altera o saldo e grava o movimento junto.
 * Não usa o saldo_posterior do último movimento: vendas sincronizadas depois entram com a
 * data real da venda (retroativa), então a ordem dos ids não é a ordem dos fatos.
 */
class InventarioService
{
    /** Primeira movimentação registrada: antes dela o histórico não existe. */
    public static function inicioDoHistorico(): ?Carbon
    {
        $primeira = MovimentacaoEstoque::min('created_at');

        return $primeira ? Carbon::parse($primeira) : null;
    }

    /** Apaga os itens do rascunho e calcula de novo. */
    public function calcular(Inventario $inventario): void
    {
        $fimDoDia = $inventario->data->copy()->endOfDay();

        DB::transaction(function () use ($inventario, $fimDoDia) {
            $inventario->itens()->delete();

            // Saldo atual: produto simples usa o próprio campo; produto com variação soma as variantes
            $saldoVariantes = ProdutoVariante::query()
                ->selectRaw('produto_id, SUM(estoque) as total')
                ->groupBy('produto_id')
                ->pluck('total', 'produto_id');

            // Tudo o que entrou/saiu depois da data, por produto (todas as variantes juntas)
            $depois = MovimentacaoEstoque::query()
                ->where('created_at', '>', $fimDoDia)
                ->selectRaw('produto_id, SUM(quantidade) as total')
                ->groupBy('produto_id')
                ->pluck('total', 'produto_id');

            // Custo na data: o do último movimento até a data; sem movimento, o custo atual do cadastro
            $custoNaData = MovimentacaoEstoque::query()
                ->whereIn('id', MovimentacaoEstoque::query()
                    ->where('created_at', '<=', $fimDoDia)
                    ->selectRaw('MAX(id)')
                    ->groupBy('produto_id'))
                ->pluck('custo_unitario', 'produto_id');

            $agora = now();
            $linhas = [];

            Produto::query()
                // produto cadastrado depois da data não existia no estoque (sem data de cadastro: entra)
                ->where(fn ($q) => $q->whereNull('created_at')->orWhere('created_at', '<=', $fimDoDia))
                ->orderBy('id')
                ->select(['id', 'unidade_comercial', 'estoque', 'tem_variacao', 'preco_custo'])
                ->chunk(500, function ($produtos) use (&$linhas, $inventario, $saldoVariantes, $depois, $custoNaData, $agora) {
                    foreach ($produtos as $produto) {
                        $atual = $produto->tem_variacao
                            ? (float) ($saldoVariantes[$produto->id] ?? 0)
                            : (float) $produto->estoque;

                        $quantidade = round($atual - (float) ($depois[$produto->id] ?? 0), 3);

                        if ($quantidade == 0.0 && !$inventario->incluir_zerados) {
                            continue;
                        }

                        $custo = round((float) ($custoNaData[$produto->id] ?? $produto->preco_custo ?? 0), 6);

                        $linhas[] = [
                            'inventario_id'        => $inventario->id,
                            'produto_id'           => $produto->id,
                            'unidade'              => strtoupper(trim((string) $produto->unidade_comercial)) ?: 'UN',
                            'quantidade_calculada' => $quantidade,
                            'quantidade'           => $quantidade,
                            'custo_unitario'       => $custo,
                            'valor_total'          => round($quantidade * $custo, 2),
                            'created_at'           => $agora,
                            'updated_at'           => $agora,
                        ];
                    }
                });

            foreach (array_chunk($linhas, 500) as $lote) {
                InventarioItem::insert($lote);
            }

            $inventario->recalcularTotal();
        });
    }
}
