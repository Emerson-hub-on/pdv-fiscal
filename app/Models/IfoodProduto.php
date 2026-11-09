<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item do cardápio do iFood importado para o sistema. "produto_id/produto_variante_id" é o produto do
 * cadastro do sistema que ele representa (assimilação), usado nos pedidos e, no futuro, para emitir a venda.
 * "preco" começa igual ao do iFood e o operador pode mudar.
 */
class IfoodProduto extends Model
{
    protected $table = 'ifood_produtos';

    protected $fillable = [
        'ifood_item_id', 'ifood_product_id', 'catalogo_id', 'categoria', 'nome', 'descricao',
        'codigo_externo', 'ean', 'preco_ifood', 'preco', 'imagem_url', 'status',
        'produto_id', 'produto_variante_id', 'importado_em', 'cadastrado_automaticamente',
    ];

    protected $casts = [
        'preco_ifood'  => 'decimal:2',
        'preco'        => 'decimal:2',
        'importado_em' => 'datetime',
        'cadastrado_automaticamente' => 'boolean',
    ];

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProdutoVariante::class, 'produto_variante_id');
    }

    public function nomeProdutoSistema(): ?string
    {
        if (!$this->produto) {
            return null;
        }

        $rotulo = $this->variante?->rotulo();

        return $rotulo ? "{$this->produto->nome} — {$rotulo}" : $this->produto->nome;
    }

    public function paraTela(): array
    {
        return [
            'id'             => $this->id,
            'ifood_item_id'  => $this->ifood_item_id,
            'categoria'      => $this->categoria,
            'nome'           => $this->nome,
            'descricao'      => $this->descricao,
            'codigo_externo' => $this->codigo_externo,
            'ean'            => $this->ean,
            'preco_ifood'    => (float) $this->preco_ifood,
            'preco'          => (float) $this->preco,
            'imagem_url'     => $this->imagem_url,
            'status'         => $this->status,
            'produto_id'     => $this->produto_id,
            'produto_variante_id' => $this->produto_variante_id,
            'produto_nome'   => $this->nomeProdutoSistema(),
            'cadastrado_automaticamente' => $this->cadastrado_automaticamente,
        ];
    }
}
