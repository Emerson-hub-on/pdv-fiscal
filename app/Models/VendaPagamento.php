<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendaPagamento extends Model
{
    protected $fillable = ['venda_id', 'forma_pagamento', 'valor'];

    public function venda()
    {
        return $this->belongsTo(Venda::class);
    }
}