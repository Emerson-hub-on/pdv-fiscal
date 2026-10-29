<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioItem extends Model
{
    protected $table = 'inventario_itens';

    protected $fillable = ['inventario_id', 'produto_id', 'unidade', 'quantidade_calculada', 'quantidade', 'custo_unitario', 'valor_total'];

    protected $casts = [
        'quantidade_calculada' => 'decimal:3',
        'quantidade'           => 'decimal:3',
        'custo_unitario'       => 'decimal:6',
        'valor_total'          => 'decimal:2',
    ];

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(Inventario::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
