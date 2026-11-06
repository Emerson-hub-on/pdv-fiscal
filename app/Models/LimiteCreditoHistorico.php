<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cada concessão/alteração do limite de crédito (convênio) de um cliente: quem mudou, quando e de quanto para quanto. */
class LimiteCreditoHistorico extends Model
{
    protected $table = 'limites_credito_historico';

    protected $fillable = ['cliente_id', 'ativo', 'limite_anterior', 'limite_novo', 'dias', 'observacao', 'user_id'];

    protected $casts = [
        'ativo' => 'boolean',
        'limite_anterior' => 'decimal:2',
        'limite_novo' => 'decimal:2',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
