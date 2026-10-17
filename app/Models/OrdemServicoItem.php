<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdemServicoItem extends Model
{
    protected $table = 'ordem_servico_itens';

    protected $fillable = [
        'ordem_servico_id', 'produto_id', 'produto_variante_id', 'codigo_barras',
        'descricao', 'unidade', 'quantidade', 'valor_unitario',
    ];

    protected $casts = [
        'quantidade'     => 'decimal:3',
        'valor_unitario' => 'decimal:2',
    ];

    public function ordem(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class, 'ordem_servico_id');
    }

    public function total(): float
    {
        return round((float) $this->quantidade * (float) $this->valor_unitario, 2);
    }
}
