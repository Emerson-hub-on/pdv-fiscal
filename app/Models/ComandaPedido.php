<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pedido de uma mesa, montado pelo garçom na tela dele. Uma mesa ocupada pode ter vários.
 * Tempo de espera = agora − enviado_em, até a mesa ir para "Pedido entregue" (entregue_em).
 * Quando a mesa volta para "Disponível", os pedidos dela são fechados (fechado_em).
 */
class ComandaPedido extends Model
{
    protected $table = 'comanda_pedidos';

    protected $fillable = ['uuid', 'mesa_id', 'garcom_id', 'total', 'enviado_em'];

    protected $casts = [
        'total'       => 'decimal:2',
        'enviado_em'  => 'datetime',
        'entregue_em' => 'datetime',
        'fechado_em'  => 'datetime',
    ];

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class);
    }

    public function garcom(): BelongsTo
    {
        return $this->belongsTo(User::class, 'garcom_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ComandaPedidoItem::class, 'pedido_id');
    }

    /** Pedidos que ainda estão na mesa (ela não voltou para "Disponível"). */
    public function scopeAbertos($query)
    {
        return $query->whereNull('fechado_em');
    }

    public function paraTela(int $numero): array
    {
        return [
            'id'          => $this->id,
            'numero'      => $numero, // 1º, 2º... pedido da mesa
            'enviado_em'  => $this->enviado_em->toIso8601String(),
            'entregue_em' => $this->entregue_em?->toIso8601String(),
            'garcom'      => $this->garcom?->name,
            'total'       => (float) $this->total,
            'itens'       => $this->itens->map(fn (ComandaPedidoItem $i) => [
                'nome'       => $i->nome,
                'quantidade' => $i->quantidade,
                'observacao' => $i->observacao,
            ])->all(),
        ];
    }
}
