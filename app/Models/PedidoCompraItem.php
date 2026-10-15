<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoCompraItem extends Model
{
    protected $table = 'pedido_compra_itens';

    protected $fillable = [
        'pedido_compra_id',
        'produto_id',
        'produto_variante_id',
        'codigo_barras',
        'descricao',
        'unidade',
        'quantidade',
    ];

    protected $casts = [
        'quantidade' => 'decimal:3',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoCompra::class, 'pedido_compra_id');
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProdutoVariante::class, 'produto_variante_id');
    }
}
