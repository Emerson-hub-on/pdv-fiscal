<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProdutoLote extends Model
{
    protected $table = 'produto_lotes';

    protected $fillable = [
        'produto_id', 'produto_variante_id', 'entrada_nota_item_id',
        'lote', 'validade', 'quantidade_inicial', 'quantidade_atual',
    ];

    protected $casts = [
        'validade'           => 'date',
        'quantidade_inicial' => 'decimal:3',
        'quantidade_atual'   => 'decimal:3',
    ];

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }

    public function itemEntrada()
    {
        return $this->belongsTo(EntradaNotaItem::class, 'entrada_nota_item_id');
    }
}
