<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conta (comanda) de uma mesa: abre no 1º pedido e fecha quando o cliente paga,
 * pelo garçom na mesa ou pelo operador no balcão (ver App\Services\ComandaService).
 */
class Comanda extends Model
{
    public const ABERTA    = 'aberta';
    public const PAGA      = 'paga';
    public const CANCELADA = 'cancelada'; // mesa liberada sem nada a cobrar (todos os pedidos cancelados)

    protected $table = 'comandas';

    protected $fillable = ['mesa_id', 'status', 'total', 'troco', 'aberta_em'];

    protected $casts = [
        'total'      => 'decimal:2',
        'troco'      => 'decimal:2',
        'aberta_em'  => 'datetime',
        'fechada_em' => 'datetime',
        'dados_nfce' => 'array', // CPF/nome do cliente para a NFC-e (Pedidos Restaurante)
    ];

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(ComandaPedido::class)->orderBy('enviado_em')->orderBy('id');
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(ComandaPagamento::class);
    }

    public function fechadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fechada_por');
    }

    /** Recalcula o total: soma dos pedidos não cancelados. */
    public function recalcularTotal(): void
    {
        $this->update([
            'total' => (float) $this->pedidos()->where('status', '!=', ComandaPedido::CANCELADO)->sum('total'),
        ]);
    }

    /**
     * Conta para a tela de fechamento: itens somados de todos os pedidos (não cancelados),
     * total e, se já paga, os pagamentos.
     */
    public function paraFechamento(): array
    {
        $this->loadMissing(['mesa', 'pedidos.itens', 'pedidos.garcom', 'pagamentos']);
        $pedidos = $this->pedidos->where('status', '!=', ComandaPedido::CANCELADO)->sortBy('id')->values();

        // Resumo pedido a pedido (1ª etapa do fechamento)
        $resumo = $pedidos->map(fn (ComandaPedido $p, int $i) => [
            'id'         => $p->id,
            'numero'     => $i + 1,
            'status'     => ComandaPedido::STATUS[$p->status]['rotulo'] ?? $p->status,
            'enviado_em' => $p->enviado_em?->toIso8601String(),
            'garcom'     => $p->garcom?->name,
            'cliente'    => $p->cliente_nome,
            'total'      => (float) $p->total,
            'itens'      => $p->itens->map(fn (ComandaPedidoItem $item) => [
                'nome'           => $item->nome,
                'quantidade'     => (int) $item->quantidade,
                'preco_unitario' => (float) $item->preco_unitario,
                'subtotal'       => (float) $item->subtotal,
                'observacao'     => $item->observacao,
            ])->values(),
        ]);

        // Mesmo item com o mesmo preço vira uma linha só (a observação não importa na conta)
        $itens = $pedidos->flatMap->itens
            ->groupBy(fn ($i) => $i->nome . '|' . $i->preco_unitario)
            ->map(fn ($grupo) => [
                'nome'           => $grupo->first()->nome,
                'quantidade'     => (int) $grupo->sum('quantidade'),
                'preco_unitario' => (float) $grupo->first()->preco_unitario,
                'subtotal'       => round((float) $grupo->sum('subtotal'), 2),
            ])
            ->values();

        return [
            'id'         => $this->id,
            'status'     => $this->status,
            'mesa'       => $this->mesa?->titulo(),
            'mesa_id'    => $this->mesa_id,
            'aberta_em'  => $this->aberta_em?->toIso8601String(),
            'pedidos'    => $pedidos->count(),
            'resumo'     => $resumo,
            'clientes'   => $pedidos->pluck('cliente_nome')->filter()->unique()->values(),
            'itens'      => $itens,
            'total'      => (float) $this->total,
            'troco'      => (float) $this->troco,
            'pagamentos' => $this->pagamentos->map(fn ($p) => ['descricao' => $p->descricao, 'valor' => (float) $p->valor])->values(),
        ];
    }
}
