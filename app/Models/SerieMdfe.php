<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SerieMdfe extends Model
{
    protected $table = 'series_mdfe';

    protected $fillable = ['serie', 'numero_atual', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];

    public function scopeAtivas($query)
    {
        return $query->where('ativo', true);
    }
}
