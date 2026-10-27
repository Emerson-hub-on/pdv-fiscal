<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MdfeEvento extends Model
{
    protected $table = 'mdfe_eventos';

    public const CANCELAMENTO       = 'cancelamento';
    public const ENCERRAMENTO       = 'encerramento';
    public const INCLUSAO_CONDUTOR  = 'inclusao_condutor';
    public const INCLUSAO_DFE       = 'inclusao_dfe';

    protected $fillable = [
        'mdfe_id', 'tipo', 'codigo_evento', 'sequencia', 'justificativa',
        'dados', 'protocolo', 'xml', 'operador_id', 'registrado_em',
    ];

    protected $casts = [
        'dados'         => 'array',
        'registrado_em' => 'datetime',
    ];

    public function mdfe(): BelongsTo
    {
        return $this->belongsTo(Mdfe::class);
    }

    public function operador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operador_id');
    }
}
