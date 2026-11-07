<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categoria do cardápio das comandas (Bebidas, Lanches...). Separada da categoria do cadastro do produto.
 */
class CardapioCategoria extends Model
{
    protected $table = 'cardapio_categorias';

    protected $fillable = ['nome', 'ordem'];

    protected $casts = ['ordem' => 'integer'];

    public function itens(): HasMany
    {
        return $this->hasMany(CardapioItem::class, 'cardapio_categoria_id');
    }

    public function scopeOrdenadas($query)
    {
        return $query->orderBy('ordem')->orderBy('nome');
    }
}
