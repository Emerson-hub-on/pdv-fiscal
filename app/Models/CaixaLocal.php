<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CaixaLocal extends Model
{
    protected $connection = 'sqlite_local';
    protected $table = 'caixas_local';
    protected $guarded = [];

    protected $casts = [
        'data_abertura' => 'datetime',
        'data_fechamento' => 'datetime',
        'valor_abertura' => 'decimal:2',
        'valor_fechamento_informado' => 'decimal:2',
        'valor_fechamento_esperado' => 'decimal:2',
        'sync_pendente' => 'boolean',
    ];

    public function pdv()
    {
        return $this->belongsTo(PdvCache::class, 'pdv_id');
    }

    public static function aberto(int $operadorId): ?self
    {
        return static::where('operador_id', $operadorId)
            ->where('status', 'aberto')
            ->first();
    }

    // Total das vendas deste caixa, calculado só com os dados locais (exceto canceladas)
    public function totalVendido(): float
    {
        return (float) DB::connection('sqlite_local')->table('vendas_pendentes')
            ->where(function ($q) {
                $q->where('caixa_uuid', $this->uuid);

                // vendas feitas antes desta mudança só tinham o id do servidor
                if ($this->id_central) {
                    $q->orWhere('caixa_id_central', $this->id_central);
                }
            })
            ->where('status', '!=', 'cancelada')
            ->sum('total');
    }
}