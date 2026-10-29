<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpedGeracao extends Model
{
    protected $table = 'sped_geracoes';

    protected $fillable = [
        'competencia', 'finalidade', 'destacar_icms', 'saldo_credor_anterior',
        'icms_recolher', 'saldo_credor_transportar', 'arquivo', 'total_linhas', 'user_id',
    ];

    protected $casts = [
        'competencia'              => 'date',
        'destacar_icms'            => 'boolean',
        'saldo_credor_anterior'    => 'decimal:2',
        'icms_recolher'            => 'decimal:2',
        'saldo_credor_transportar' => 'decimal:2',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
