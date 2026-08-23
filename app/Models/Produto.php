<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Produto extends Model
{
    protected $fillable = [
        'nome', 'descricao',
        'categoria_id', 'marca_id', 'grupo_id',
        'codigo_interno', 'codigo_barras',
        'ncm_id', 'cest_id',
        'unidade_comercial', 'unidade_tributavel',
        'origem_mercadoria', 'class_trib_ibs_cbs',
        'preco_venda', 'preco_custo',
        'tem_variacao', 'produto_balanca', 'estoque', 'estoque_minimo',
        'ativo', 'tributacao_id',
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

    public function cest(): BelongsTo
    {
        return $this->belongsTo(Cest::class);
    }

    protected $casts = [
        'tem_variacao' => 'boolean',
        'ativo' => 'boolean',
        'preco_venda' => 'decimal:2',
        'preco_custo' => 'decimal:2',
        'produto_balanca' => 'boolean',
    ];

    public function tributacao()
    {
        return $this->belongsTo(Tributacao::class);
    }

    public static function proximoCodigoInterno(): string
    {
        // Pega o maior codigo_interno que seja puramente numérico
        $ultimo = static::whereRaw('codigo_interno REGEXP "^[0-9]+$"')
            ->orderByRaw('CAST(codigo_interno AS UNSIGNED) DESC')
            ->value('codigo_interno');

        return $ultimo ? (string)((int)$ultimo + 1) : '1';
    }

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