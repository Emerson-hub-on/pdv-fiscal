<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormaPagamento extends Model
{
    protected $table = 'formas_pagamento';

    protected $fillable = ['descricao', 'meio_pagamento', 'ind_pag', 'ordem', 'ativo'];

    public const MEIOS = [
        '01' => 'Dinheiro',
        '02' => 'Cheque',
        '03' => 'Cartão de crédito',
        '04' => 'Cartão de débito',
        '05' => 'Crédito loja',
        '10' => 'Vale alimentação',
        '11' => 'Vale refeição',
        '12' => 'Vale presente',
        '13' => 'Vale combustível',
        '15' => 'Boleto bancário',
        '16' => 'Depósito bancário',
        '17' => 'PIX',
        '18' => 'Transferência / carteira digital',
        '19' => 'Programa de fidelidade',
        '90' => 'Sem pagamento',
        '99' => 'Outros',
    ];

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