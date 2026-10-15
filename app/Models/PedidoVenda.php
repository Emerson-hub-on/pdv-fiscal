<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedidoVenda extends Model
{
    protected $table = 'pedidos_venda';

    protected $fillable = [
        'numero',
        'data_pedido',
        'previsao_entrega',
        'operador_id',
        'cliente_id',
        'forma_pagamento_id',
        'valor_total',
        'status',
        'descricao',
    ];

    protected $casts = [
        'data_pedido' => 'date',
        'previsao_entrega' => 'date',
        'valor_total' => 'decimal:2',
    ];

    public function operador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operador_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function formaPagamento(): BelongsTo
    {
        return $this->belongsTo(FormaPagamento::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(PedidoVendaItem::class)->orderBy('id');
    }

    /**
     * Próximo número sequencial. Chamar dentro de transação (lockForUpdate);
     * o índice unique em `numero` é a segunda barreira contra duplicidade.
     */
    public static function proximoNumero(): int
    {
        return ((int) static::lockForUpdate()->max('numero')) + 1;
    }

    public function numeroExibicao(): string
    {
        return str_pad((string) $this->numero, 6, '0', STR_PAD_LEFT);
    }

    public function podeEditar(): bool
    {
        return $this->status === 'finalizado';
    }

    public function podeCancelar(): bool
    {
        return $this->status === 'finalizado';
    }
}
