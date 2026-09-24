<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotaFiscal extends Model
{
    protected $table = 'notas_fiscais';
    protected $fillable = [
        'cliente_id',
        'operador_id',
        'natureza_operacao',
        'finalidade',
        'tipo_operacao',
        'origem_tipo',
        'venda_id',
        'valor_desconto',
        'valor_frete',
        'cfop_saida_id',
        'forma_pagamento_id',
    ];

    protected $casts = [
        'valor_produtos' => 'decimal:2',
        'valor_desconto' => 'decimal:2',
        'valor_frete' => 'decimal:2',
        'valor_total' => 'decimal:2',
        'emitida_em' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function operador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operador_id');
    }

    public function serieNfe(): BelongsTo
    {
        return $this->belongsTo(SerieNfe::class, 'serie_nfe_id');
    }

    public function cfopSaida(): BelongsTo
    {
        return $this->belongsTo(CfopSaida::class);
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(NotaFiscalItem::class);
    }

    public function scopeRascunhos($query)
    {
        return $query->where('status', 'rascunho');
    }

    /**
     * Recalcula os totais da nota com base nos itens atuais.
     */
    public function recalcularTotais(): void
    {
        $produtos = $this->itens()->sum('valor_total');

        // Atribuição direta (não update()) — 'valor_produtos' e 'valor_total'
        // ficam de propósito fora do $fillable, então mass assignment não
        // gravaria nada aqui (mesmo padrão usado em NotaFiscalController::emitir()).
        $this->valor_produtos = $produtos;
        $this->valor_total = $produtos - $this->valor_desconto + $this->valor_frete;
        $this->save();
    }

    public function formaPagamento(): BelongsTo
    {
        return $this->belongsTo(FormaPagamento::class);
    }
}