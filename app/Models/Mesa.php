<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Mesa das comandas. Cadastro só no painel (Comandas → Mesas → Lista de Mesas);
 * o garçom escolhe a mesa e muda a situação (status) dela.
 */
class Mesa extends Model
{
    public const DISPONIVEL = 'disponivel';
    public const AGUARDANDO = 'aguardando';
    public const ENTREGUE   = 'entregue';

    /** Situações na ordem do atendimento: rótulo e classes Tailwind (selo, ponto e borda da tab). */
    public const STATUS = [
        self::DISPONIVEL => [
            'rotulo' => 'Disponível',
            'selo'   => 'bg-green-50 text-green-700',
            'ponto'  => 'bg-green-500',
            'borda'  => 'ring-green-200',
        ],
        self::AGUARDANDO => [
            'rotulo' => 'Aguardando pedido',
            'selo'   => 'bg-amber-50 text-amber-700',
            'ponto'  => 'bg-amber-500',
            'borda'  => 'ring-amber-300',
        ],
        self::ENTREGUE => [
            'rotulo' => 'Pedido entregue',
            'selo'   => 'bg-blue-50 text-blue-700',
            'ponto'  => 'bg-blue-500',
            'borda'  => 'ring-blue-200',
        ],
    ];

    protected $table = 'mesas';

    protected $fillable = ['numero', 'nome', 'lugares', 'ativo'];

    protected $casts = [
        'numero'       => 'integer',
        'lugares'      => 'integer',
        'ativo'        => 'boolean',
        'status_desde' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::DISPONIVEL,
        'ativo'  => true,
    ];

    public function alteradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_por');
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(ComandaPedido::class);
    }

    /** Pedidos que ainda estão na mesa (desde que ela saiu de "Disponível"), do mais antigo ao mais novo. */
    public function pedidosAbertos(): HasMany
    {
        return $this->pedidos()->abertos()->orderBy('enviado_em')->orderBy('id');
    }

    public function scopeOrdenadas($query)
    {
        return $query->orderBy('numero');
    }

    /** "Mesa 5" ou "Mesa 5 · Varanda". */
    public function titulo(): string
    {
        return 'Mesa ' . $this->numero . ($this->nome ? ' · ' . $this->nome : '');
    }

    public function dadosStatus(): array
    {
        return self::STATUS[$this->status] ?? self::STATUS[self::DISPONIVEL];
    }

    /**
     * Muda a situação e zera o relógio da mesa. Efeito nos pedidos dela:
     *  - Pedido entregue: os que estavam esperando saem da espera (entregue_em = agora);
     *  - Disponível: a mesa liberou, os pedidos dela são fechados (fechado_em = agora).
     */
    public function mudarStatus(string $status, ?int $userId): void
    {
        if ($status === $this->status) {
            return;
        }

        DB::transaction(function () use ($status, $userId) {
            $agora = now();

            if ($status === self::ENTREGUE) {
                $this->pedidos()->abertos()->whereNull('entregue_em')->update(['entregue_em' => $agora]);
            } elseif ($status === self::DISPONIVEL) {
                $this->pedidos()->abertos()->update(['fechado_em' => $agora]);
            }

            $this->forceFill([
                'status'       => $status,
                'status_desde' => $agora,
                'status_por'   => $userId,
            ])->save();
        });
    }

    /** Dados que a tela do garçom e a lista do painel usam no JavaScript. */
    public function paraTela(): array
    {
        return [
            'id'           => $this->id,
            'numero'       => $this->numero,
            'nome'         => $this->nome,
            'titulo'       => $this->titulo(),
            'lugares'      => $this->lugares,
            'status'       => $this->status,
            'status_desde' => $this->status_desde?->toIso8601String(),
            'status_por'   => $this->alteradoPor?->name,
            // Pedidos da mesa com o tempo de espera de cada um (carregue pedidosAbertos.itens e .garcom antes)
            'pedidos'      => $this->relationLoaded('pedidosAbertos')
                ? $this->pedidosAbertos->values()->map(fn (ComandaPedido $p, int $i) => $p->paraTela($i + 1))->all()
                : [],
        ];
    }
}
