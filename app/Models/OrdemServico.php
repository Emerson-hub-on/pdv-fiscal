<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdemServico extends Model
{
    protected $table = 'ordens_servico';

    protected $fillable = [
        'data_abertura', 'previsao_entrega', 'cliente_id', 'cliente_nome', 'cliente_telefone', 'cliente_documento',
        'operador_id', 'tecnico_id',
        'forma_pagamento_id', 'status', 'prioridade',
        'equipamento', 'marca', 'modelo', 'numero_serie', 'acessorios',
        'defeito_relatado', 'condicao_entrada', 'laudo_tecnico', 'servico_executado', 'observacoes', 'termo_responsabilidade',
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

    /**
     * Texto padrão do termo impresso na OS. O operador pode alterá-lo em cada OS (aba "Termo");
     * quando ele não altera, a OS fica sem texto próprio e usa este. Vale conferir a redação
     * (prazo de retirada, garantia) com seu contador/advogado.
     */
    public const TERMO_PADRAO = 'Declaro que as informações acima conferem com o estado em que entreguei o equipamento e que autorizo a análise e o reparo descritos. '
        . 'Equipamentos não retirados em até 90 dias após o aviso de conclusão poderão ser destinados para cobrir custos do serviço. '
        . 'A garantia cobre apenas o serviço executado e as peças substituídas nesta ordem de serviço, e não cobre mau uso, quedas, oxidação ou violação do lacre.';

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

    /** Cliente avulso = sem cadastro (cliente_id vazio); os dados ficam na própria OS. */
    public function clienteAvulso(): bool
    {
        return $this->cliente_id === null;
    }

    public function nomeCliente(): ?string
    {
        return $this->cliente
            ? ($this->cliente->nome_exibicao ?? $this->cliente->nome)
            : ($this->cliente_nome ?: null);
    }

    public function telefoneCliente(): ?string
    {
        return $this->cliente ? ($this->cliente->telefone ?: null) : ($this->cliente_telefone ?: null);
    }

    public function documentoCliente(): ?string
    {
        if ($this->cliente) {
            return $this->cliente->cpf_cnpj_formatado ?: null;
        }

        $d = preg_replace('/\D/', '', (string) $this->cliente_documento);

        return match (strlen($d)) {
            11      => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d),
            14      => preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d),
            default => null,
        };
    }

    public function numeroExibicao(): string
    {
        return $this->numero ? str_pad((string) $this->numero, 6, '0', STR_PAD_LEFT) : '—';
    }

    /** Termo que vai na impressão: o da própria OS, se foi alterado, ou o padrão. */
    public function termoImpresso(): string
    {
        $proprio = trim((string) $this->termo_responsabilidade);

        return $proprio !== '' ? $proprio : self::TERMO_PADRAO;
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