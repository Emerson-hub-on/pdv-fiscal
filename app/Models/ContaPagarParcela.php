<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContaPagarParcela extends Model
{
    protected $table = 'contas_pagar_parcelas';

    protected $fillable = [
        'conta_pagar_id', 'numero', 'vencimento', 'valor',
        'data_pagamento', 'valor_pago', 'forma_pagamento_id',
    ];

    protected $casts = [
        'vencimento'     => 'date',
        'data_pagamento' => 'date',
        'valor'          => 'decimal:2',
        'valor_pago'     => 'decimal:2',
    ];

    public function conta(): BelongsTo
    {
        return $this->belongsTo(ContaPagar::class, 'conta_pagar_id');
    }

    public function formaPagamento(): BelongsTo
    {
        return $this->belongsTo(FormaPagamento::class, 'forma_pagamento_id');
    }

    public function isPaga(): bool
    {
        return $this->data_pagamento !== null;
    }

    public function isVencida(): bool
    {
        return ! $this->isPaga() && $this->vencimento->isBefore(today());
    }
}
