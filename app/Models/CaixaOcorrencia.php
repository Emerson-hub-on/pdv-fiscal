<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ocorrência de um caixa (desconto, cancelamento de NFC-e, autorização do supervisor), vinda do SQLite do caixa. */
class CaixaOcorrencia extends Model
{
    protected $table = 'caixa_ocorrencias';

    protected $guarded = [];

    protected $casts = [
        'valor' => 'decimal:2',
        'liberado_operador' => 'boolean',
        'ocorrida_em' => 'datetime',
    ];
}
