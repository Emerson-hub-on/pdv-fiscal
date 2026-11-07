<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Item de um pedido da comanda: nome e preço ficam gravados como estavam no momento do pedido. */
class ComandaPedidoItem extends Model
{
    protected $table = 'comanda_pedido_itens';

    protected $fillable = [
        'pedido_id', 'cardapio_item_id', 'produto_id', 'produto_variante_id',
        'nome', 'quantidade', 'preco_unitario', 'subtotal', 'observacao',
    ];

    protected $casts = [
        'quantidade'     => 'integer',
        'preco_unitario' => 'decimal:2',
        'subtotal'       => 'decimal:2',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(ComandaPedido::class, 'pedido_id');
    }
}
