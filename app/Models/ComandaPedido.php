<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pedido de uma mesa (montado pelo garçom) — e, no futuro, do iFood (coluna origem).
 * Situação: preparo → pronto → entregue → concluido (a conta da mesa foi paga) | cancelado.
 * Na Lista de Pedidos: Em preparo = preparo; Pronto / A caminho = pronto e entregue;
 * Concluído = concluido e cancelado.
 */
class ComandaPedido extends Model
{
    public const PREPARO   = 'preparo';
    public const PRONTO    = 'pronto';
    public const ENTREGUE  = 'entregue';
    public const CONCLUIDO = 'concluido';
    public const CANCELADO = 'cancelado';

    /** Rótulo e classes Tailwind de cada situação (botões da Lista de Pedidos e selos do garçom). */
    public const STATUS = [
        self::PREPARO   => ['rotulo' => 'Em preparo', 'ativo' => 'bg-amber-400 text-white ring-amber-400',   'selo' => 'bg-amber-50 text-amber-700'],
        self::PRONTO    => ['rotulo' => 'Pronto',     'ativo' => 'bg-blue-600 text-white ring-blue-600',     'selo' => 'bg-blue-50 text-blue-700'],
        self::ENTREGUE  => ['rotulo' => 'Entregue',   'ativo' => 'bg-indigo-600 text-white ring-indigo-600', 'selo' => 'bg-indigo-50 text-indigo-700'],
        self::CONCLUIDO => ['rotulo' => 'Concluído',  'ativo' => 'bg-green-600 text-white ring-green-600',   'selo' => 'bg-green-50 text-green-700'],
        self::CANCELADO => ['rotulo' => 'Cancelado',  'ativo' => 'bg-red-600 text-white ring-red-600',       'selo' => 'bg-red-50 text-red-700'],
    ];

    /** Situações que o operador muda à mão na Lista de Pedidos (concluído só pelo pagamento da conta). */
    public const STATUS_MANUAIS = [self::PREPARO, self::PRONTO, self::ENTREGUE, self::CANCELADO];

    /** Pedidos que ainda contam na mesa (a conta não foi paga). */
    public const STATUS_ABERTOS = [self::PREPARO, self::PRONTO, self::ENTREGUE];

    protected $table = 'comanda_pedidos';

    protected $fillable = ['uuid', 'comanda_id', 'origem', 'status', 'mesa_id', 'garcom_id', 'cliente_nome', 'total', 'enviado_em'];

    protected $casts = [
        'total'        => 'decimal:2',
        'enviado_em'   => 'datetime',
        'pronto_em'    => 'datetime',
        'entregue_em'  => 'datetime',
        'concluido_em' => 'datetime',
        'cancelado_em' => 'datetime',
    ];

    protected $attributes = [
        'origem' => 'mesa',
        'status' => self::PREPARO,
    ];

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class);
    }

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class);
    }

    public function garcom(): BelongsTo
    {
        return $this->belongsTo(User::class, 'garcom_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ComandaPedidoItem::class, 'pedido_id');
    }

    /** Pedidos que ainda estão na mesa (conta não paga, não cancelados). */
    public function scopeAbertos($query)
    {
        return $query->whereIn('status', self::STATUS_ABERTOS);
    }

    /** Muda a situação registrando quando e quem. */
    public function mudarStatus(string $status, ?int $userId): void
    {
        $campoData = [
            self::PRONTO    => 'pronto_em',
            self::ENTREGUE  => 'entregue_em',
            self::CONCLUIDO => 'concluido_em',
            self::CANCELADO => 'cancelado_em',
        ][$status] ?? null;

        $dados = ['status' => $status, 'status_por' => $userId];
        if ($campoData) {
            $dados[$campoData] = now();
        }
        // voltou para preparo: tira as marcas das etapas seguintes
        if ($status === self::PREPARO) {
            $dados += ['pronto_em' => null, 'entregue_em' => null];
        }

        $this->forceFill($dados)->save();
    }

    /** Para a janela da mesa na tela do garçom. */
    public function paraTela(int $numero): array
    {
        return [
            'id'          => $this->id,
            'numero'      => $numero, // 1º, 2º... pedido da mesa
            'status'      => $this->status,
            'enviado_em'  => $this->enviado_em->toIso8601String(),
            'pronto_em'   => $this->pronto_em?->toIso8601String(),
            'entregue_em' => $this->entregue_em?->toIso8601String(),
            'garcom_id'   => $this->garcom_id,
            'garcom'      => $this->garcom?->name,
            'cliente'     => $this->cliente_nome,
            'total'       => (float) $this->total,
            'itens'       => $this->itens->map(fn (ComandaPedidoItem $i) => [
                'nome'       => $i->nome,
                'quantidade' => $i->quantidade,
                'observacao' => $i->observacao,
            ])->all(),
        ];
    }

    /** Para a Lista de Pedidos do painel. */
    public function paraLista(): array
    {
        return [
            'id'           => $this->id,
            'origem'       => $this->origem,
            'status'       => $this->status,
            'mesa'         => $this->mesa?->titulo(),
            'mesa_id'      => $this->mesa_id,
            'comanda_id'   => $this->comanda_id,
            'conta_status' => $this->comanda?->status,
            'cliente'      => $this->cliente_nome,
            'garcom'       => $this->garcom?->name,
            'total'        => (float) $this->total,
            'enviado_em'   => $this->enviado_em?->toIso8601String(),
            'pronto_em'    => $this->pronto_em?->toIso8601String(),
            'entregue_em'  => $this->entregue_em?->toIso8601String(),
            'concluido_em' => $this->concluido_em?->toIso8601String(),
            'cancelado_em' => $this->cancelado_em?->toIso8601String(),
            'itens'        => $this->itens->map(fn (ComandaPedidoItem $i) => [
                'nome'           => $i->nome,
                'quantidade'     => $i->quantidade,
                'preco_unitario' => (float) $i->preco_unitario,
                'subtotal'       => (float) $i->subtotal,
                'observacao'     => $i->observacao,
            ])->all(),
            'pagamentos'   => $this->comanda && $this->comanda->status === Comanda::PAGA
                ? $this->comanda->pagamentos->map(fn ($p) => ['descricao' => $p->descricao, 'valor' => (float) $p->valor])->all()
                : [],
            'troco'        => $this->comanda?->status === Comanda::PAGA ? (float) $this->comanda->troco : 0,
            // O pagamento é da conta da mesa inteira (todos os pedidos), não deste pedido
            'conta_total'   => $this->comanda ? (float) $this->comanda->total : null,
            'conta_pedidos' => $this->comanda ? (int) ($this->comanda->pedidos_na_conta ?? 0) : 0,
        ];
    }
}
