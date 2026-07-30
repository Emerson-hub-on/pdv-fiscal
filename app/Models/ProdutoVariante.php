<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProdutoVariante extends Model
{
    protected $table = 'produto_variantes';

    protected $fillable = [
        'produto_id', 'cor', 'tamanho', 'sku', 'estoque', 'estoque_minimo',
    ];

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }
}