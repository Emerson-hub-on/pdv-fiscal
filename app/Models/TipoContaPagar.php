<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoContaPagar extends Model
{
    protected $table = 'tipos_conta_pagar';

    protected $fillable = ['descricao', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];
}
