<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdemServico extends Model
{
    protected $table = 'ordens_servico';

    protected $fillable = [
        'data_abertura', 'previsao_entrega', 'cliente_id', 'operador_id', 'tecnico_id',
        'forma_pagamento_id', 'status', 'prioridade',
        'equipamento', 'marca', 'modelo', 'numero_serie', 'acessorios',
        'defeito_relatado', 'condicao_entrada', 'laudo_tecnico', 'servico_executado', 'observacoes',
        'garantia_dias', 'valor_servico', 'desconto', 'valor_total',
    ];

    protected $casts = [
        'data_abertura'    => 'date',
        'previsao_entrega' => 'date',
        'concluida_em'     => 'datetime',
        'entregue_em'      => 'datetime',
        'valor_servico'    => 'decimal:2',
        'desconto'         => 'decimal:2',
        'valor_total'      => 'decimal:2',
    ];

    public const STATUS = [
        'aberta'               => 'Aberta',
        'em_analise'           => 'Em análise',
        'aguardando_aprovacao' => 'Aguard. aprovação',
        'aguardando_peca'      => 'Aguard. peça',
        'em_andamento'         => 'Em andamento',
        'concluida'            => 'Concluída',
        'entregue'             => 'Entregue',
        'cancelada'            => 'Cancelada',
    ];

    /** Próximos passos permitidos a partir de cada status (atalhos do menu "Ações"). */
    public const FLUXO = [
        'aberta'               => ['em_analise' => 'Iniciar análise'],
        'em_analise'           => ['aguardando_aprovacao' => 'Enviar para aprovação', 'em_andamento' => 'Iniciar reparo'],
        'aguardando_aprovacao' => ['em_andamento' => 'Orçamento aprovado — iniciar reparo'],
        'aguardando_peca'      => ['em_andamento' => 'Peça chegou — retomar reparo'],
        'em_andamento'         => ['aguardando_peca' => 'Aguardar peça', 'concluida' => 'Concluir serviço'],
        'concluida'            => ['entregue' => 'Registrar entrega ao cliente'],
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function operador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operador_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function formaPagamento(): BelongsTo
    {
        return $this->belongsTo(FormaPagamento::class, 'forma_pagamento_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(OrdemServicoItem::class);
    }

    public function numeroExibicao(): string
    {
        return $this->numero ? str_pad((string) $this->numero, 6, '0', STR_PAD_LEFT) : '—';
    }

    public function statusTexto(): string
    {
        return self::STATUS[$this->status] ?? (string) $this->status;
    }

    public function podeEditar(): bool
    {
        return ! in_array($this->status, ['entregue', 'cancelada'], true);
    }

    public function podeCancelar(): bool
    {
        return $this->podeEditar();
    }

    public function proximosStatus(): array
    {
        return self::FLUXO[$this->status] ?? [];
    }

    public function atrasada(): bool
    {
        return $this->previsao_entrega
            && $this->previsao_entrega->isPast()
            && ! $this->previsao_entrega->isToday()
            && ! in_array($this->status, ['concluida', 'entregue', 'cancelada'], true);
    }

    public function valorPecas(): float
    {
        return round($this->itens->sum(fn ($i) => $i->total()), 2);
    }
}
