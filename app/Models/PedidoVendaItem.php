<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoVendaItem extends Model
{
    protected $table = 'pedido_venda_itens';

    protected $fillable = [
        'pedido_venda_id',
        'produto_id',
        'produto_variante_id',
        'codigo_barras',
        'descricao',
        'unidade',
        'quantidade',
        'valor_unitario',
    ];

    protected $casts = [
        'quantidade' => 'decimal:3',
        'valor_unitario' => 'decimal:2',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoVenda::class, 'pedido_venda_id');
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProdutoVariante::class, 'produto_variante_id');
    }

    public function total(): float
    {
        return round((float) $this->quantidade * (float) $this->valor_unitario, 2);
    }
}
