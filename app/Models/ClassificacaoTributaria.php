<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassificacaoTributaria extends Model
{
    protected $table = 'classificacoes_tributarias';

    protected $fillable = [
        'codigo',
        'descricao',
        'cst_codigo',
        'cst_descricao',
    ];

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class, 'class_trib_ibs_cbs_id');
    }
}