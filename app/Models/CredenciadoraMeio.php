<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredenciadoraMeio extends Model
{
    protected $table = 'credenciadora_meios';

    protected $fillable = ['credenciadora_id', 'meio_pagamento'];

    public function credenciadora(): BelongsTo
    {
        return $this->belongsTo(Credenciadora::class);
    }
}
