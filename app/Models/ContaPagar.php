<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContaPagar extends Model
{
    protected $table = 'contas_pagar';

    protected $fillable = [
        'tipo_conta_pagar_id', 'fornecedor_id', 'entrada_nota_id',
        'descricao', 'numero_documento', 'data_conta', 'valor_total',
        'observacao', 'status',
    ];

    protected $casts = [
        'data_conta'  => 'date',
        'valor_total' => 'decimal:2',
    ];

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoContaPagar::class, 'tipo_conta_pagar_id');
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function parcelas(): HasMany
    {
        return $this->hasMany(ContaPagarParcela::class, 'conta_pagar_id');
    }

    /**
     * Número que identifica a dívida na listagem: o nº do documento, quando informado;
     * senão um código interno único (CP-000123), para nenhuma conta ficar sem identificação.
     */
    public function numeroExibicao(): string
    {
        return $this->numero_documento ?: 'CP-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function temParcelaPaga(): bool
    {
        return $this->parcelas->contains(fn ($p) => $p->isPaga());
    }

    /** Soma das parcelas ainda não pagas. */
    public function saldoAberto(): float
    {
        return (float) $this->parcelas->reject(fn ($p) => $p->isPaga())->sum('valor');
    }

    /** Parcela pendente com o vencimento mais próximo. */
    public function proximaParcela(): ?ContaPagarParcela
    {
        return $this->parcelas
            ->reject(fn ($p) => $p->isPaga())
            ->sortBy(fn ($p) => $p->vencimento->timestamp)
            ->first();
    }

    /** paga | vencida | aberta (usado na listagem). */
    public function situacao(): string
    {
        if ($this->status === 'paga') {
            return 'paga';
        }

        $prox = $this->proximaParcela();

        return $prox && $prox->isVencida() ? 'vencida' : 'aberta';
    }

    /** Atualiza o status conforme as parcelas: paga quando não sobra nenhuma pendente. */
    public function recalcularStatus(): void
    {
        $temPendente = $this->parcelas()->whereNull('data_pagamento')->exists();

        $this->update(['status' => $temPendente ? 'aberta' : 'paga']);
        $this->unsetRelation('parcelas');
    }
}