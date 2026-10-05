<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaixaCache extends Model
{
    protected $connection = 'sqlite_local';
    protected $table = 'caixas_cache';
    public $incrementing = false; // o id vem do central
    protected $guarded = [];      // gravado só pelo SyncService

    protected $casts = [
        'data_abertura' => 'datetime',
        'valor_abertura' => 'decimal:2',
    ];

    public function pdv()
    {
        return $this->belongsTo(PdvCache::class, 'pdv_id');
    }

    // Equivalente local do Caixa::aberto(): a view do PDV continua usando $caixa->pdv->nome etc.
    public static function aberto(int $operadorId): ?self
    {
        return static::where('operador_id', $operadorId)
            ->where('status', 'aberto')
            ->first();
    }
}