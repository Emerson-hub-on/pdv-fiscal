<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CfopSaida extends Model
{
    protected $table = 'cfop_saida';

    protected $fillable = [
        'codigo', 'descricao', 'movimenta_estoque', 'ativo',
        'natureza_operacao_padrao', 'finalidade_padrao',
    ];

    protected $casts = [
        'movimenta_estoque' => 'boolean',
        'ativo' => 'boolean',
    ];

    public function notasFiscais(): HasMany
    {
        return $this->hasMany(NotaFiscal::class);
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }
}