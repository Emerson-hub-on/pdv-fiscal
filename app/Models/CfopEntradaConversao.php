<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CfopEntradaConversao extends Model
{
    use HasFactory;

    protected $table = 'cfop_entrada_conversoes';

    protected $fillable = [
        'operacao_entrada_id', 
        'cfop_origem', 
        'cfop_entrada_id'
    ];

    /**
     * Relacionamento: Operação de entrada associada.
     */
    public function operacao(): BelongsTo
    {
        return $this->belongsTo(OperacaoEntrada::class, 'operacao_entrada_id');
    }

    /**
     * Relacionamento: CFOP de entrada associado.
     */
    public function cfopEntrada(): BelongsTo
    {
        return $this->belongsTo(CfopEntrada::class, 'cfop_entrada_id');
    }
}