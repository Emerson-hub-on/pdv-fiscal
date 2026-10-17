<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unidade extends Model
{
    protected $table = 'unidades';

    protected $fillable = ['sigla', 'descricao', 'permite_fracionado', 'ativo'];

    protected $casts = [
        'permite_fracionado' => 'boolean',
        'ativo' => 'boolean',
    ];

    public function scopeAtivas($query)
    {
        return $query->where('ativo', true);
    }

    /** A unidade (pela sigla) aceita quantidade/estoque com casas decimais? */
    public static function permiteFracionado(?string $sigla): bool
    {
        if (! $sigla) {
            return false;
        }

        return (bool) static::where('sigla', strtoupper(trim($sigla)))->value('permite_fracionado');
    }

    /** Quantos produtos usam esta unidade (comercial ou tributável). */
    public function totalDeProdutos(): int
    {
        return Produto::where('unidade_comercial', $this->sigla)
            ->orWhere('unidade_tributavel', $this->sigla)
            ->count();
    }
}
