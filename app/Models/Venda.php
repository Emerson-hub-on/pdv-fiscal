<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venda extends Model
{
    protected $fillable = [
        'caixa_id', 'cliente_id', 'operador_id', 'total',
        'forma_pagamento', 'status',
        'chave_nfe', 'protocolo_nfe', 'motivo_cancelamento',
        'motivo_rejeicao', 'numero_nfce', 'serie_nfce',
    ];

    protected $casts = [
        'total' => 'decimal:2',
    ];

    public function caixa()
    {
        return $this->belongsTo(Caixa::class);
    }

    public function operador()
    {
        return $this->belongsTo(User::class, 'operador_id');
    }

    public function itens()
    {
        return $this->hasMany(VendaItem::class);
    }
}