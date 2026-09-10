<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SerieNfe extends Model
{
    protected $table = 'series_nfe';

    protected $fillable = [
        'serie',
        'numero_atual',
        'descricao',
        'ativa',
    ];

    protected $casts = [
        'ativa' => 'boolean',
    ];

    public function notasFiscais(): HasMany
    {
        return $this->hasMany(NotaFiscal::class, 'serie_nfe_id');
    }

    public function scopeAtivas($query)
    {
        return $query->where('ativa', true);
    }
}