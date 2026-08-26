<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassificacaoIpi extends Model
{
    protected $table = 'classificacoes_ipi';

    protected $fillable = [
        'codigo',
        'descricao',
        'cenq',
        'aliquota',
    ];

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class, 'ipi_id');
    }
}