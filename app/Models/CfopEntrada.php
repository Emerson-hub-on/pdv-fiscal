<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CfopEntrada extends Model
{
    use HasFactory;

    protected $table = 'cfops_entrada';

    protected $fillable = [
        'codigo', 
        'descricao', 
        'ativo'
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    /**
     * Relacionamento: Conversões vinculadas a este CFOP de entrada.
     */
    public function conversoes(): HasMany
    {
        return $this->hasMany(CfopEntradaConversao::class, 'cfop_entrada_id');
    }
}