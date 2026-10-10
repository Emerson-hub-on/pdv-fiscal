<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperacaoEntrada extends Model
{
    use HasFactory;

    protected $table = 'operacoes_entrada';

    protected $fillable = [
        'codigo', 
        'descricao', 
        'movimenta_estoque', 
        'ordem', 
        'ativo'
    ];

    protected $casts = [
        'movimenta_estoque' => 'boolean', 
        'ativo' => 'boolean',
    ];

    /**
     * Relacionamento: Conversões vinculadas a esta operação de entrada.
     */
    public function conversoes(): HasMany
    {
        return $this->hasMany(CfopEntradaConversao::class, 'operacao_entrada_id');
    }
}