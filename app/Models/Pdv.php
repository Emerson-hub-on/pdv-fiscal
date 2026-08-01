<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pdv extends Model
{
    protected $fillable = [
        'nome', 'serie_nfce', 'numero_atual_nfce', 'csc', 'csc_id', 'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function caixas()
    {
        return $this->hasMany(Caixa::class);
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }
}