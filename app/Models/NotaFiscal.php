<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotaFiscal extends Model
{
    protected $fillable = [
        'cliente_id',
        'operador_id',
        'natureza_operacao',
        'finalidade',
        'tipo_operacao',
        'origem_tipo',
        'venda_id',
        'valor_desconto',
        'valor_frete',
    ];

    protected $casts = [
        'valor_produtos' => 'decimal:2',
        'valor_desconto' => 'decimal:2',
        'valor_frete' => 'decimal:2',
        'valor_total' => 'decimal:2',
        'emitida_em' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function operador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operador_id');
    }

    public function serieNfe(): BelongsTo
    {
        return $this->belongsTo(SerieNfe::class, 'serie_nfe_id');
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(NotaFiscalItem::class);
    }

    public function scopeRascunhos($query)
    {
        return $query->where('status', 'rascunho');
    }

    /**
     * Recalcula os totais da nota com base nos itens atuais.
     */
    public function recalcularTotais(): void
    {
        $produtos = $this->itens()->sum('valor_total');

        $this->update([
            'valor_produtos' => $produtos,
            'valor_total' => $produtos - $this->valor_desconto + $this->valor_frete,
        ]);
    }
}