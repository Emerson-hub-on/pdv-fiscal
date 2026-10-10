<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormaPagamento extends Model
{
    protected $table = 'formas_pagamento';

    protected $fillable = [
        'descricao', 
        'meio_pagamento', 
        'ind_pag', 
        'ordem', 
        'ativo', 
        'uso_saida', 
        'uso_entrada'];

    protected $casts = [
        'ativo'         => 'boolean', 
        'uso_saida'     => 'boolean', 
        'uso_entrada'   => 'boolean'];

    public const MEIOS = [
        '01' => 'Dinheiro',
        '02' => 'Cheque',
        '03' => 'Cartão de crédito',
        '04' => 'Cartão de débito',
        '05' => 'Cartão da loja (private label), crediário',
        '10' => 'Vale alimentação',
        '11' => 'Vale refeição',
        '12' => 'Vale presente',
        '13' => 'Vale combustível',
        '14' => 'Duplicata mercantil',
        '15' => 'Boleto bancário',
        '16' => 'Depósito bancário',
        '17' => 'PIX dinâmico',
        '18' => 'Transferência / carteira digital',
        '19' => 'Programa de fidelidade, cashback',
        '20' => 'PIX estático',
        '21' => 'Crédito em loja',
        '23' => 'PIX automático',
        '24' => 'TEF – Book Transfer',
        '90' => 'Sem pagamento',
        '91' => 'Pagamento posterior',
        '99' => 'Outros',
    ];

    public function scopeParaSaida($query)
    {
        return $query->where('uso_saida', true);
    }

    public function scopeParaEntrada($query)
    {
        return $query->where('uso_entrada', true);
    }


    public function notasFiscais(): HasMany
    {
        return $this->hasMany(NotaFiscal::class);
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }
}