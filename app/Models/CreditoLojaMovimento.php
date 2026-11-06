<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditoLojaMovimento extends Model
{
    protected $table = 'credito_loja_movimentos';

    protected $fillable = ['credito_loja_id', 'tipo', 'valor', 'nota_fiscal_id', 'venda_uuid', 'descricao', 'user_id'];

    protected $casts = ['valor' => 'decimal:2'];

    public const ROTULOS = [
        'credito'      => 'Crédito gerado',
        'uso'          => 'Usado',
        'estorno'      => 'Estorno de uso',
        'cancelamento' => 'Cancelado',
    ];

    public function credito(): BelongsTo
    {
        return $this->belongsTo(CreditoLoja::class, 'credito_loja_id');
    }

    public function notaFiscal(): BelongsTo
    {
        return $this->belongsTo(NotaFiscal::class);
    }
}
