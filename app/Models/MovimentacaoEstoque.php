<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro imutável de uma alteração de estoque. Quem grava é o EstoqueService.
 */
class MovimentacaoEstoque extends Model
{
    protected $table = 'movimentacoes_estoque';

    public const UPDATED_AT = null;

    protected $fillable = [
        'produto_id', 'produto_variante_id', 'variacao', 'tipo',
        'quantidade', 'saldo_anterior', 'saldo_posterior', 'custo_unitario',
        'documento_tipo', 'documento_id', 'documento_rotulo',
        'observacao', 'user_id',
    ];

    protected $casts = [
        'quantidade'      => 'float',
        'saldo_anterior'  => 'float',
        'saldo_posterior' => 'float',
        'custo_unitario'  => 'float',
    ];

    /** Tipos de movimentação e como aparecem nas telas. */
    public const TIPOS = [
        'estoque_inicial'  => 'Estoque inicial',
        'ajuste_manual'    => 'Ajuste manual',
        'nfe_saida'        => 'NF-e (saída)',
        'nfe_entrada'      => 'NF-e (entrada)',
        'nfe_cancelamento' => 'Cancelamento de NF-e',
        'venda'            => 'Venda no PDV',
        'venda_cancelada'  => 'Venda cancelada',
        'entrada_compra'   => 'Entrada de nota',
        'transferencia'    => 'Transferência',
    ];

    protected static function booted(): void
    {
        // Livro-razão: o histórico não se edita nem se apaga.
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
