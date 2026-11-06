<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Linha do histórico de uma conta a receber (aba "Histórico" da conta). */
class ContaReceberHistorico extends Model
{
    protected $table = 'contas_receber_historico';

    protected $fillable = [
        'conta_receber_id', 'parcela_id', 'parcela_numero', 'tipo', 'origem',
        'valor', 'forma', 'descricao', 'user_id', 'ocorrido_em',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'ocorrido_em' => 'datetime',
    ];

    public const TIPOS = [
        'criacao'      => ['Conta criada', 'bg-slate-100 text-slate-700'],
        'alteracao'    => ['Conta alterada', 'bg-blue-100 text-blue-800'],
        'baixa'        => ['Baixa', 'bg-green-100 text-green-800'],
        'estorno'      => ['Estorno', 'bg-red-100 text-red-700'],
        'cancelamento' => ['Venda cancelada', 'bg-amber-100 text-amber-800'],
    ];

    public const ORIGENS = ['painel' => 'Painel', 'caixa' => 'Caixa', 'sistema' => 'Sistema'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Registra uma operação na conta. $parcela opcional (nº e id da parcela envolvida). */
    public static function registrar(ContaReceber|int $conta, string $tipo, array $dados = [], ?ContaReceberParcela $parcela = null): self
    {
        $userId = $dados['user_id'] ?? auth()->id();

        return self::create([
            'conta_receber_id' => $conta instanceof ContaReceber ? $conta->id : $conta,
            'parcela_id'       => $parcela?->id,
            'parcela_numero'   => $parcela?->numero ?: null,
            'tipo'             => $tipo,
            'origem'           => $dados['origem'] ?? 'painel',
            'valor'            => $dados['valor'] ?? null,
            'forma'            => $dados['forma'] ?? null,
            'descricao'        => isset($dados['descricao']) ? mb_substr($dados['descricao'], 0, 500) : null,
            'user_id'          => $userId && User::whereKey($userId)->exists() ? $userId : null,
            'ocorrido_em'      => $dados['ocorrido_em'] ?? now(),
        ]);
    }
}
