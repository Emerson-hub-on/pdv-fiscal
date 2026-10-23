<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendaItem extends Model
{
    protected $table = 'venda_itens';

    protected $fillable = [
        'venda_id', 'produto_id', 'produto_variante_id',
        'quantidade', 'preco_unitario', 'subtotal', 'desconto',
        'descricao', 'codigo_produto', 'ncm', 'cfop', 'cst_csosn',
        'valor_bruto', 'desconto_efetivo', 'bc_icms', 'aliquota_icms', 'valor_icms',
        'valor_pis', 'valor_cofins', 'valor_ibs', 'valor_cbs', 'impostos_registrados_em',
    ];

    protected $casts = [
        'preco_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'valor_bruto' => 'decimal:2',
        'desconto_efetivo' => 'decimal:2',
        'bc_icms' => 'decimal:2',
        'aliquota_icms' => 'decimal:4',
        'valor_icms' => 'decimal:2',
        'valor_pis' => 'decimal:2',
        'valor_cofins' => 'decimal:2',
        'valor_ibs' => 'decimal:2',
        'valor_cbs' => 'decimal:2',
        'impostos_registrados_em' => 'datetime',
    ];

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }

    public function variante()
    {
        return $this->belongsTo(ProdutoVariante::class, 'produto_variante_id');
    }
}