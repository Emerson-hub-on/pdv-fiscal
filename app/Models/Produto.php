<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produto extends Model
{
    protected $fillable = [
        'nome', 'descricao',
        'categoria_id', 'marca_id', 'grupo_id',
        'codigo_interno', 'codigo_barras',
        'ncm_id', 'cest', 'cfop_padrao',
        'unidade_comercial', 'unidade_tributavel',
        'origem_mercadoria', 'csosn', 'class_trib_ibs_cbs',
        'preco_venda', 'preco_custo',
        'tem_variacao', 'produto_balanca', 'estoque', 'estoque_minimo',
        'ativo',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function marca()
    {
        return $this->belongsTo(Marca::class);
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class);
    }

    public function ncm()
    {
        return $this->belongsTo(Ncm::class);
    }

    protected $casts = [
        'tem_variacao' => 'boolean',
        'ativo' => 'boolean',
        'preco_venda' => 'decimal:2',
        'preco_custo' => 'decimal:2',
        'produto_balanca' => 'boolean',
    ];

    public function variantes()
    {
        return $this->hasMany(ProdutoVariante::class);
    }

    /**
     * Estoque total do produto, seja simples ou somado das variantes.
     */
    public function getEstoqueTotalAttribute(): int
    {
        return $this->tem_variacao
            ? $this->variantes()->sum('estoque')
            : $this->estoque;
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    /**
     * Nunca permite exclusão real — só inativação.
     */
    public function inativar(): void
    {
        $this->update(['ativo' => false]);
    }

    public function reativar(): void
    {
        $this->update(['ativo' => true]);
    }
}