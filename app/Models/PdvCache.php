<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdvCache extends Model
{
    protected $connection = 'sqlite_local';
    protected $table = 'pdvs_cache';
    public $incrementing = false; // o id vem do central
    protected $guarded = [];      // gravado só pelo SyncService

    protected $casts = [
        'ativo' => 'boolean',
    ];
}