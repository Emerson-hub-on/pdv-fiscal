<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContagemEstoqueItem extends Model
{
    protected $table = 'contagem_estoque_itens';

    protected $fillable = [
        'contagem_estoque_id', 'produto_id', 'produto_variante_id', 'descricao', 'unidade',
        'saldo_sistema', 'quantidade_contada', 'diferenca', 'custo_unitario', 'valor_diferenca',
    ];

    protected $casts = [
        'saldo_sistema'      => 'float',
        'quantidade_contada' => 'float',
        'diferenca'          => 'float',
        'custo_unitario'     => 'float',
        'valor_diferenca'    => 'float',
    ];

    public function contagem(): BelongsTo
    {
        return $this->belongsTo(ContagemEstoque::class, 'contagem_estoque_id');
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProdutoVariante::class, 'produto_variante_id');
    }
}
