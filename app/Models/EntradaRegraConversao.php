<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Regra de conversão criada pelo usuário numa operação de entrada:
 * CFOP + CST/CSOSN do fornecedor -> CFOP + CST/CSOSN de entrada.
 * Tem prioridade sobre as regras automáticas (CfopEntradaConversao e CstEntradaConversao).
 */
class EntradaRegraConversao extends Model
{
    protected $table = 'entrada_regras_conversao';

    protected $fillable = [
        'operacao_entrada_id',
        'regime',
        'cfop_origem',
        'tipo_origem',
        'codigo_origem',
        'cfop_entrada_id',
        'codigo_entrada',
        'gera_credito',
    ];

    protected $casts = [
        'gera_credito' => 'boolean',
    ];

    /** Chave usada na busca: CFOP|tipo:código (ex.: 5102|cst:00). */
    public static function chave(?string $cfopOrigem, ?string $tipo, ?string $codigo): string
    {
        return "{$cfopOrigem}|{$tipo}:{$codigo}";
    }

    public function operacao(): BelongsTo
    {
        return $this->belongsTo(OperacaoEntrada::class, 'operacao_entrada_id');
    }

    public function cfopEntrada(): BelongsTo
    {
        return $this->belongsTo(CfopEntrada::class, 'cfop_entrada_id');
    }
}
