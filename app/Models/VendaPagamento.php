<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendaPagamento extends Model
{
    protected $fillable = ['venda_id', 'forma_pagamento', 'valor', 'credito_loja_id'];

    public function venda()
    {
        return $this->belongsTo(Venda::class);
    }
}