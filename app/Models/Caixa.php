<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Caixa extends Model
{
    protected $fillable = [
        'operador_id', 'data_abertura', 'valor_abertura',
        'data_fechamento', 'valor_fechamento_informado', 'valor_fechamento_esperado',
        'status', 'observacao',
    ];

    protected $casts = [
        'data_abertura' => 'datetime',
        'data_fechamento' => 'datetime',
        'valor_abertura' => 'decimal:2',
        'valor_fechamento_informado' => 'decimal:2',
        'valor_fechamento_esperado' => 'decimal:2',
    ];

    public function operador()
    {
        return $this->belongsTo(User::class, 'operador_id');
    }

    public function vendas()
    {
        return $this->hasMany(Venda::class);
    }

    /**
     * Retorna o caixa aberto do operador informado, se houver.
     */
    public static function aberto(int $operadorId): ?self
    {
        return static::where('operador_id', $operadorId)
            ->where('status', 'aberto')
            ->first();
    }

    public function totalVendido(): float
    {
        return $this->vendas()->where('status', 'emitida')->sum('total');
    }
}