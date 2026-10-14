<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContaReceber extends Model
{
    protected $table = 'contas_receber';

    protected $fillable = [
        'tipo_conta_receber_id', 'cliente_id', 'venda_id',
        'descricao', 'numero_documento', 'data_conta', 'valor_total',
        'observacao', 'status',
    ];

    protected $casts = [
        'data_conta'  => 'date',
        'valor_total' => 'decimal:2',
    ];

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoContaReceber::class, 'tipo_conta_receber_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** Nome do cliente para exibição (aceita nome_exibicao, nome ou razao_social no model Cliente). */
    public static function rotuloCliente(?Cliente $cliente): ?string
    {
        return $cliente ? ($cliente->nome_exibicao ?? $cliente->nome ?? $cliente->razao_social ?? null) : null;
    }

    public function nomeCliente(): ?string
    {
        return self::rotuloCliente($this->cliente);
    }

    public function parcelas(): HasMany
    {
        return $this->hasMany(ContaReceberParcela::class, 'conta_receber_id');
    }

    /**
     * Número que identifica a dívida na listagem: o nº do documento, quando informado;
     * senão um código interno único (CR-000123), para nenhuma conta ficar sem identificação.
     */
    public function numeroExibicao(): string
    {
        return $this->numero_documento ?: 'CR-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function temParcelaRecebida(): bool
    {
        return $this->parcelas->contains(fn ($p) => $p->isRecebida());
    }

    /** Soma das parcelas ainda não recebidas. */
    public function saldoAberto(): float
    {
        return (float) $this->parcelas->reject(fn ($p) => $p->isRecebida())->sum('valor');
    }

    /** Parcela pendente com o vencimento mais próximo. */
    public function proximaParcela(): ?ContaReceberParcela
    {
        return $this->parcelas
            ->reject(fn ($p) => $p->isRecebida())
            ->sortBy(fn ($p) => $p->vencimento->timestamp)
            ->first();
    }

    /** recebida | vencida | aberta (usado na listagem). */
    public function situacao(): string
    {
        if ($this->status === 'recebida') {
            return 'recebida';
        }

        $prox = $this->proximaParcela();

        return $prox && $prox->isVencida() ? 'vencida' : 'aberta';
    }

    /** Atualiza o status conforme as parcelas: recebida quando não sobra nenhuma pendente. */
    public function recalcularStatus(): void
    {
        $temPendente = $this->parcelas()->whereNull('data_recebimento')->exists();

        $this->update(['status' => $temPendente ? 'aberta' : 'recebida']);
        $this->unsetRelation('parcelas');
    }
}