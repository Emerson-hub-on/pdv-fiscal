<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntradaNotaItem extends Model
{
    protected $table = 'entrada_nota_itens';

    protected $fillable = [
        'entrada_nota_id', 'produto_id', 'produto_variante_id',
        'codigo_fornecedor', 'descricao', 'unidade',
        'quantidade', 'valor_unitario', 'valor_desconto', 'valor_total',
        'lote', 'validade',
    ];

    protected $casts = [
        'validade'       => 'date',
        'quantidade'     => 'decimal:3',
        'valor_unitario' => 'decimal:4',
        'valor_desconto' => 'decimal:2',
        'valor_total'    => 'decimal:2',
    ];

    public function entrada()
    {
        return $this->belongsTo(EntradaNota::class, 'entrada_nota_id');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }
}
