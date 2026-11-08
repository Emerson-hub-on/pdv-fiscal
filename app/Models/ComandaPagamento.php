<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pagamento de uma conta de mesa (uma conta pode ter várias formas). */
class ComandaPagamento extends Model
{
    protected $table = 'comanda_pagamentos';

    protected $fillable = ['comanda_id', 'forma_pagamento', 'descricao', 'meio_pagamento', 'valor'];

    protected $casts = ['valor' => 'decimal:2'];

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class);
    }
}
