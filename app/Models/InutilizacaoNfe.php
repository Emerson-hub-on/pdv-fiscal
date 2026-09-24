<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InutilizacaoNfe extends Model
{
    protected $table = 'inutilizacoes_nfe';

    protected $fillable = [
        'serie_nfe_id', 'serie', 'numero_inicial', 'numero_final',
        'justificativa', 'status', 'protocolo', 'motivo', 'operador_id',
    ];

    public function serieNfe(): BelongsTo
    {
        return $this->belongsTo(SerieNfe::class, 'serie_nfe_id');
    }

    public function operador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operador_id');
    }
}