<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassificacaoPisCofins extends Model
{
    protected $table = 'classificacoes_pis_cofins';

    protected $fillable = [
        'codigo',
        'descricao',
        'aliquota_pis',
        'aliquota_cofins',
    ];

    protected $casts = [
        'aliquota_pis' => 'decimal:4',
        'aliquota_cofins' => 'decimal:4',
    ];

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class, 'pis_cofins_id');
    }
}