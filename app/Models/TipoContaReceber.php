<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoContaReceber extends Model
{
    protected $table = 'tipos_conta_receber';

    protected $fillable = ['descricao', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];
}
