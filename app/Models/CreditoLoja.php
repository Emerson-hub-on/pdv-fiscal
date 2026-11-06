<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Crédito em loja (haver do cliente / vale-troca). Ver App\Services\CreditoLojaService. */
class CreditoLoja extends Model
{
    protected $table = 'creditos_loja';

    protected $fillable = ['codigo', 'cliente_id', 'nota_fiscal_id', 'valor', 'saldo', 'status', 'observacao'];

    protected $casts = [
        'valor' => 'decimal:2',
        'saldo' => 'decimal:2',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** NF-e de entrada da troca que gerou o crédito. */
    public function notaFiscal(): BelongsTo
    {
        return $this->belongsTo(NotaFiscal::class);
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(CreditoLojaMovimento::class)->orderBy('id');
    }

    public function scopeDisponiveis($query)
    {
        return $query->where('status', 'ativo')->where('saldo', '>', 0);
    }
}
