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
     * observacao, user_id (padrão: usuário logado), data (padrão: agora),
     * tocar_updated_at (padrão: true).
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

            // Por padrão grava updated_at (igual ao increment/decrement do Eloquent). As vendas do
            // PDV passam tocar_updated_at=false: o catálogo do caixa é puxado por updated_at e
            // sobrescreve o estoque do cache, então uma venda não pode marcar o produto como "alterado".
            $alvo->timestamps = (bool) ($contexto['tocar_updated_at'] ?? true);
            $alvo->save();

            $custo = $alvo instanceof Produto
                ? $alvo->preco_custo
                : Produto::whereKey($produtoId)->value('preco_custo');

            $dados = [
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
            ];

            // Data real do fato (ex.: vendida_em de uma venda sincronizada depois).
            // Só entra se informada: created_at nulo explícito impediria o preenchimento automático.
            if (! empty($contexto['data'])) {
                $dados['created_at'] = $contexto['data'];
            }

            return MovimentacaoEstoque::create($dados);
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

    // ------------------------------------------------------------------
    // Vendas do PDV (servidor central)
    // ------------------------------------------------------------------

    /**
     * Baixa no estoque central os itens de uma venda do PDV que acabou de ser
     * sincronizada. IDEMPOTENTE: se o caixa reenviar a mesma venda, o que já foi
     * registrado para aquele uuid é ignorado (não baixa duas vezes).
     *
     * $itens: linhas com produto_id, produto_variante_id (ou null) e quantidade.
     * $contexto: user_id (operador da venda), data (vendida_em), documento_rotulo.
     *
     * Retorna quantas movimentações foram gravadas.
     */
    public function baixarVenda(string $vendaUuid, iterable $itens, array $contexto = []): int
    {
        return $this->aplicarVenda(self::VENDA, -1, $vendaUuid, $itens, $contexto);
    }

    /**
     * Devolve ao estoque central os itens de uma venda cancelada. Também idempotente.
     * Recebe os itens (e não busca os movimentos originais) para devolver certo
     * mesmo as vendas feitas antes do histórico existir.
     */
    public function devolverVenda(string $vendaUuid, iterable $itens, array $contexto = []): int
    {
        return $this->aplicarVenda(self::VENDA_CANCELADA, 1, $vendaUuid, $itens, $contexto);
    }

    private function aplicarVenda(string $tipo, int $sinal, string $vendaUuid, iterable $itens, array $contexto): int
    {
        // A mesma variante pode aparecer em mais de uma linha da venda: soma antes de registrar
        $agrupados = [];

        foreach ($itens as $item) {
            $produtoId = (int) data_get($item, 'produto_id');
            $varianteId = data_get($item, 'produto_variante_id') ? (int) data_get($item, 'produto_variante_id') : null;
            $chave = $produtoId . ':' . ($varianteId ?? 0);

            $agrupados[$chave] ??= [$produtoId, $varianteId, 0.0];
            $agrupados[$chave][2] += (float) data_get($item, 'quantidade');
        }

        $contexto = array_merge($contexto, [
            'documento_tipo' => 'venda',
            'documento_id'   => $vendaUuid,
        ]);

        return DB::transaction(function () use ($tipo, $sinal, $vendaUuid, $agrupados, $contexto) {
            $gravados = 0;

            foreach ($agrupados as [$produtoId, $varianteId, $quantidade]) {
                // Trava a linha ANTES de conferir, para dois envios simultâneos não passarem juntos
                $varianteId
                    ? ProdutoVariante::whereKey($varianteId)->lockForUpdate()->value('id')
                    : Produto::whereKey($produtoId)->lockForUpdate()->value('id');

                $jaRegistrado = MovimentacaoEstoque::where('tipo', $tipo)
                    ->where('documento_tipo', 'venda')
                    ->where('documento_id', $vendaUuid)
                    ->where('produto_id', $produtoId)
                    ->when($varianteId, fn ($q) => $q->where('produto_variante_id', $varianteId), fn ($q) => $q->whereNull('produto_variante_id'))
                    ->exists();

                if ($jaRegistrado) {
                    continue;
                }

                if ($this->movimentar($produtoId, $varianteId, $sinal * $quantidade, $tipo, $contexto)) {
                    $gravados++;
                }
            }

            return $gravados;
        });
    }
}