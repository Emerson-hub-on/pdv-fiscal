<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MdfePercurso extends Model
{
    protected $table = 'mdfe_percursos';

    public $timestamps = false;

    protected $fillable = ['mdfe_id', 'uf', 'ordem'];
}
