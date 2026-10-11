<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntradaNotaItem extends Model
{
    protected $table = 'entrada_nota_itens';

    protected $fillable = [
        'entrada_nota_id', 
        'produto_id', 
        'produto_variante_id',
        'codigo_fornecedor', 
        'descricao', 
        'unidade',
        'quantidade', 
        'valor_unitario', 
        'valor_desconto', 
        'valor_total',
        'valor_outras_despesas',
        'valor_frete',
        'bc_icms',
        'valor_icms',
        'aliquota_icms',
        'valor_ipi',
        'aliquota_ipi',
        'lote', 
        'validade', 
        'cfop_origem', 
        'cst_origem', 
        'csosn_origem',
        'origem_mercadoria',
        'cfop_entrada_id', 
        'cst_csosn_entrada', 
        'gera_credito',
        'fiscal_manual',
        'qtd_por_caixa',
    ];

    protected $casts = [
        'validade'       => 'date',
        'quantidade'     => 'decimal:3',
        'valor_unitario' => 'decimal:4',
        'valor_desconto' => 'decimal:2',
        'valor_total'    => 'decimal:2',
        'valor_outras_despesas' => 'decimal:2',
        'valor_frete'           => 'decimal:2',
        'bc_icms'               => 'decimal:2',
        'valor_icms'            => 'decimal:2',
        'aliquota_icms'         => 'decimal:4',
        'valor_ipi'             => 'decimal:2',
        'aliquota_ipi'          => 'decimal:4',
        'gera_credito'   => 'boolean',
        'fiscal_manual'  => 'boolean',
        'qtd_por_caixa'  => 'integer',
    ];

    public function getQuantidadeEstoqueAttribute(): float
    {
        return (float) $this->quantidade * max((int) $this->qtd_por_caixa, 1);
    }

    public function cfopEntrada()
    {
        return $this->belongsTo(CfopEntrada::class, 'cfop_entrada_id');
    }

    public function entrada()
    {
        return $this->belongsTo(EntradaNota::class, 'entrada_nota_id');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }
}