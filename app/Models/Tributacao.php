<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tributacao extends Model
{
    protected $table = 'tributacoes';

    protected $fillable = [
        'descricao', 'crt', 'cfop', 'csosn', 'cst_icms',
        'aliquota_icms', 'observacao', 'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'aliquota_icms' => 'decimal:2',
    ];

    public function produtos()
    {
        return $this->hasMany(Produto::class);
    }

    public function labelCompleto(): string
    {
        $codigo = $this->csosn ? "CSOSN {$this->csosn}" : "CST {$this->cst_icms}";
        $aliq = $this->aliquota_icms > 0 ? " ({$this->aliquota_icms}%)" : '';
        return "{$this->descricao} — CFOP {$this->cfop} / {$codigo}{$aliq}";
    }
}