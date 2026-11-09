<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Categoria do cardápio do iFood → produto do sistema usado como modelo fiscal no cadastro automático. */
class IfoodCategoriaFiscal extends Model
{
    protected $table = 'ifood_categorias_fiscais';

    protected $fillable = ['categoria', 'produto_modelo_id'];

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_modelo_id');
    }
}
