<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntradaNota extends Model
{
    protected $table = 'entradas_nota';

    protected $fillable = [
        'fornecedor_id', 
        'user_id', 
        'tipo_entrada', 
        'status',
        'chave_acesso', 
        'modelo', 
        'serie', 
        'numero',
        'data_emissao', 
        'data_entrada', 
        'natureza_operacao',
        'valor_produtos', 
        'valor_frete', 
        'valor_desconto', 
        'valor_outras', 
        'valor_total',
        'atualizar_custo', 
        'observacao', 
        'finalizada_em',
        'operacao_entrada_id',
        'forma_pagamento_id',
    ];

    protected $casts = [
        'data_emissao'    => 'date',
        'data_entrada'    => 'date',
        'finalizada_em'   => 'datetime',
        'atualizar_custo' => 'boolean',
        'valor_produtos'  => 'decimal:2',
        'valor_frete'     => 'decimal:2',
        'valor_desconto'  => 'decimal:2',
        'valor_outras'    => 'decimal:2',
        'valor_total'     => 'decimal:2',
    ];

    public function formaPagamento()
    {
        return $this->belongsTo(FormaPagamento::class, 'forma_pagamento_id');
    }

    public function fornecedor()
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function operacao()
    {
        return $this->belongsTo(OperacaoEntrada::class, 'operacao_entrada_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function itens()
    {
        return $this->hasMany(EntradaNotaItem::class);
    }

    public function isRascunho(): bool
    {
        return $this->status === 'rascunho';
    }

    public function isFinalizada(): bool
    {
        return $this->status === 'finalizada';
    }

    /**
     * Recalcula os totais a partir dos itens já gravados.
     * valor_total dos itens já é líquido do desconto do item;
     * o desconto do cabeçalho é um desconto global da nota.
     */
    public function recalcularTotais(): void
    {
        $produtos = (float) $this->itens()->sum('valor_total');

        $this->valor_produtos = round($produtos, 2);
        $this->valor_total = round(
            $produtos + (float) $this->valor_frete + (float) $this->valor_outras - (float) $this->valor_desconto,
            2
        );
        $this->save();
    }
}
