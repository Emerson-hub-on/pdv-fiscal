<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedidoCompra extends Model
{
    protected $table = 'pedidos_compra';

    protected $fillable = [
        'numero',
        'data_pedido',
        'previsao_entrega',
        'fornecedor_id',
        'status',
        'observacao',
    ];

    protected $casts = [
        'data_pedido' => 'date',
        'previsao_entrega' => 'date',
    ];

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(PedidoCompraItem::class)->orderBy('id');
    }

    /**
     * Próximo número sequencial. Chamar dentro de transação (lockForUpdate);
     * o índice unique em `numero` é a segunda barreira contra duplicidade.
     */
    public static function proximoNumero(): int
    {
        return ((int) static::lockForUpdate()->max('numero')) + 1;
    }

    public function numeroExibicao(): string
    {
        return str_pad((string) $this->numero, 6, '0', STR_PAD_LEFT);
    }

    public function podeEditar(): bool
    {
        return $this->status === 'aberto';
    }

    public function podeEnviar(): bool
    {
        return $this->status === 'aberto';
    }

    public function podeCancelar(): bool
    {
        return in_array($this->status, ['aberto', 'enviado'], true);
    }
}
