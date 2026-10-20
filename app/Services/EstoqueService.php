<?php

namespace App\Services;

use App\Models\MovimentacaoEstoque;
use App\Models\Produto;
use App\Models\ProdutoVariante;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Único ponto do sistema que deve ALTERAR produtos.estoque / produto_variantes.estoque.
 *
 * O campo continua sendo o saldo (é ele que o PDV, a NF-e e os relatórios leem);
 * este serviço altera o campo e grava a movimentação na MESMA transação, com a
 * linha travada (lockForUpdate), então saldo e histórico nunca divergem.
 */
class EstoqueService
{
    public const ESTOQUE_INICIAL    = 'estoque_inicial';
    public const AJUSTE_MANUAL      = 'ajuste_manual';
    public const NFE_SAIDA          = 'nfe_saida';
    public const NFE_ENTRADA        = 'nfe_entrada';
    public const NFE_CANCELAMENTO   = 'nfe_cancelamento';
    public const VENDA              = 'venda';
    public const VENDA_CANCELADA    = 'venda_cancelada';
    public const ENTRADA_COMPRA     = 'entrada_compra';
    public const TRANSFERENCIA      = 'transferencia';

    /**
     * Soma $quantidade ao saldo (negativa = saída). Retorna o movimento gravado,
     * ou null se a quantidade for zero.
     *
     * $contexto (todos opcionais): documento_tipo, documento_id, documento_rotulo,
     * observacao, user_id (padrão: usuário logado).
     *
     * Não impede saldo negativo — mantém o comportamento atual do sistema.
     */
    public function movimentar(
        int $produtoId,
        ?int $varianteId,
        float $quantidade,
        string $tipo,
        array $contexto = []
    ): ?MovimentacaoEstoque {
        $quantidade = round($quantidade, 3);

        if ($quantidade == 0.0) {
            return null;
        }

        if (! array_key_exists($tipo, MovimentacaoEstoque::TIPOS)) {
            throw new InvalidArgumentException("Tipo de movimentação de estoque desconhecido: {$tipo}");
        }

        return DB::transaction(function () use ($produtoId, $varianteId, $quantidade, $tipo, $contexto) {
            $alvo = $varianteId
                ? ProdutoVariante::whereKey($varianteId)->lockForUpdate()->firstOrFail()
                : Produto::whereKey($produtoId)->lockForUpdate()->firstOrFail();

            if ($alvo instanceof ProdutoVariante && (int) $alvo->produto_id !== $produtoId) {
                throw new InvalidArgumentException('A variante informada não pertence ao produto.');
            }

            $anterior = (float) $alvo->estoque;
            $posterior = round($anterior + $quantidade, 3);

            $alvo->estoque = $posterior;
            $alvo->save();

            $custo = $alvo instanceof Produto
                ? $alvo->preco_custo
                : Produto::whereKey($produtoId)->value('preco_custo');

            return MovimentacaoEstoque::create([
                'produto_id'          => $produtoId,
                'produto_variante_id' => $varianteId,
                'variacao'            => $alvo instanceof ProdutoVariante ? ($alvo->rotulo() ?: null) : null,
                'tipo'                => $tipo,
                'quantidade'          => $quantidade,
                'saldo_anterior'      => $anterior,
                'saldo_posterior'     => $posterior,
                'custo_unitario'      => $custo,
                'documento_tipo'      => $contexto['documento_tipo'] ?? null,
                'documento_id'        => isset($contexto['documento_id']) ? (string) $contexto['documento_id'] : null,
                'documento_rotulo'    => $contexto['documento_rotulo'] ?? null,
                'observacao'          => $contexto['observacao'] ?? null,
                'user_id'             => $contexto['user_id'] ?? Auth::id(),
            ]);
        });
    }

    /**
     * Leva o saldo a um valor absoluto (caso do formulário de cadastro, onde o
     * operador digita o estoque). Registra a DIFERENÇA em relação ao saldo atual
     * do banco — não ao que a tela mostrava — e não grava nada se não mudou.
     */
    public function ajustarPara(
        int $produtoId,
        ?int $varianteId,
        float $novoSaldo,
        string $tipo = self::AJUSTE_MANUAL,
        array $contexto = []
    ): ?MovimentacaoEstoque {
        return DB::transaction(function () use ($produtoId, $varianteId, $novoSaldo, $tipo, $contexto) {
            $atual = (float) ($varianteId
                ? ProdutoVariante::whereKey($varianteId)->lockForUpdate()->value('estoque')
                : Produto::whereKey($produtoId)->lockForUpdate()->value('estoque'));

            return $this->movimentar($produtoId, $varianteId, round($novoSaldo, 3) - $atual, $tipo, $contexto);
        });
    }
}
