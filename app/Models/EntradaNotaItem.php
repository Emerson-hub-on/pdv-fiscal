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
    ];

    protected $casts = [
        'validade'       => 'date',
        'quantidade'     => 'decimal:3',
        'valor_unitario' => 'decimal:4',
        'valor_desconto' => 'decimal:2',
        'valor_total'    => 'decimal:2',
        'gera_credito'   => 'boolean',
        'fiscal_manual'  => 'boolean'
    ];

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
