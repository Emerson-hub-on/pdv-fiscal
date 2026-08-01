<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inutilizacao extends Model
{
    protected $table = 'inutilizacoes';

    protected $fillable = [
        'pdv_id', 'serie', 'numero_inicial', 'numero_final',
        'justificativa', 'status', 'protocolo', 'motivo', 'operador_id',
    ];

    public function pdv()
    {
        return $this->belongsTo(Pdv::class);
    }
}