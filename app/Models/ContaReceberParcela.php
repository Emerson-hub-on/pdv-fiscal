<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContaReceberParcela extends Model
{
    protected $table = 'contas_receber_parcelas';

    protected $fillable = [
        'conta_receber_id', 'numero', 'vencimento', 'valor',
        'data_recebimento', 'valor_recebido', 'forma_pagamento_id',
    ];

    protected $casts = [
        'vencimento'     => 'date',
        'data_recebimento' => 'date',
        'valor'          => 'decimal:2',
        'valor_recebido'     => 'decimal:2',
    ];

    public function conta(): BelongsTo
    {
        return $this->belongsTo(ContaReceber::class, 'conta_receber_id');
    }

    public function formaPagamento(): BelongsTo
    {
        return $this->belongsTo(FormaPagamento::class, 'forma_pagamento_id');
    }

    public function isRecebida(): bool
    {
        return $this->data_recebimento !== null;
    }

    public function isVencida(): bool
    {
        return ! $this->isRecebida() && $this->vencimento->isBefore(today());
    }
}
