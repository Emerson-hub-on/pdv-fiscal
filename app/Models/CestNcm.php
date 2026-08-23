<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CestNcm extends Model
{
    protected $table = 'cest_ncm';

    protected $fillable = ['cest_id', 'ncm'];
}