<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormaPagamento extends Model
{
    protected $table = 'formas_pagamento';

    protected $fillable = ['descricao', 'ordem', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];

    public function notasFiscais(): HasMany
    {
        return $this->hasMany(NotaFiscal::class);
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }
}