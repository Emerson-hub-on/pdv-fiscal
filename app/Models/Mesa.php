<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    /** Pedidos que ainda estão na mesa (conta não paga, não cancelados), do mais antigo ao mais novo. */
    public function pedidosAbertos(): HasMany
    {
        return $this->pedidos()->abertos()->orderBy('enviado_em')->orderBy('id');
    }

    /** Conta (comanda) aberta da mesa: existe do 1º pedido até o cliente pagar. */
    public function contaAberta(): HasOne
    {
        return $this->hasOne(Comanda::class)->where('status', Comanda::ABERTA)->latestOfMany();
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
     *  - Pedido entregue: os que estavam em preparo ou prontos passam a "Entregue";
     *  - Disponível: só sem conta a cobrar. Conta aberta só com pedidos cancelados é encerrada
     *    (com pedido a cobrar, a mesa é liberada ao fechar a conta: ComandaService::fecharConta).
     *
     * @throws \DomainException mesa com conta a cobrar indo para "Disponível"
     */
    public function mudarStatus(string $status, ?int $userId): void
    {
        if ($status === $this->status) {
            return;
        }

        DB::transaction(function () use ($status, $userId) {
            $agora = now();

            if ($status === self::ENTREGUE) {
                $this->pedidos()->whereIn('status', [ComandaPedido::PREPARO, ComandaPedido::PRONTO])
                    ->get()->each(fn (ComandaPedido $p) => $p->mudarStatus(ComandaPedido::ENTREGUE, $userId));
            } elseif ($status === self::DISPONIVEL) {
                $conta = $this->contaAberta()->lockForUpdate()->first();
                if ($conta && $conta->pedidos()->abertos()->exists()) {
                    throw new \DomainException('A mesa tem conta a pagar (R$ ' . number_format((float) $conta->total, 2, ',', '.') . '): feche a conta para liberar a mesa.');
                }
                $conta?->forceFill(['status' => Comanda::CANCELADA, 'fechada_em' => $agora, 'fechada_por' => $userId])->save();
            }

            $this->forceFill([
                'status'       => $status,
                'status_desde' => $agora,
                'status_por'   => $userId,
            ])->save();
        });
    }

    /**
     * Situação automática pela situação dos pedidos em aberto da mesa:
     *   algum em preparo ou pronto → Aguardando pedido; todos entregues → Pedido entregue;
     *   nenhum (ex.: todos cancelados) → Disponível (a conta vazia é encerrada).
     * O fechamento da conta libera a mesa à parte (ComandaService::fecharConta).
     */
    public function sincronizarStatus(?int $userId): void
    {
        $situacoes = $this->pedidos()->abertos()->pluck('status');

        $status = match (true) {
            $situacoes->contains(fn ($s) => in_array($s, [ComandaPedido::PREPARO, ComandaPedido::PRONTO], true)) => self::AGUARDANDO,
            $situacoes->isNotEmpty() => self::ENTREGUE,
            default => self::DISPONIVEL,
        };

        $this->mudarStatus($status, $userId);
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
            // Conta aberta (o garçom fecha por aqui)
            'conta'        => $this->relationLoaded('contaAberta') && $this->contaAberta
                ? ['id' => $this->contaAberta->id, 'total' => (float) $this->contaAberta->total]
                : null,
        ];
    }
}
