<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Produto extends Model
{
    /** TIPO_ITEM do registro 0200 do SPED. 00 (revenda) é o padrão do comércio. */
    public const TIPOS_ITEM = [
        '00' => 'Mercadoria para revenda',
        '01' => 'Matéria-prima',
        '02' => 'Embalagem',
        '03' => 'Produto em processo',
        '04' => 'Produto acabado',
        '05' => 'Subproduto',
        '06' => 'Produto intermediário',
        '07' => 'Material de uso e consumo',
        '08' => 'Ativo imobilizado',
        '09' => 'Serviços',
        '10' => 'Outros insumos',
        '99' => 'Outras',
    ];

    protected $fillable = [
        'nome', 
        'descricao',
        'categoria_id', 
        'marca_id', 
        'grupo_id',
        'codigo_interno', 
        'codigo_barras',
        'referencia',
        'ncm_id', 
        'cest_id',
        'unidade_comercial',
        'unidade_tributavel',
        'tipo_item',
        'nat_rec_pis_cofins',
        'origem_mercadoria', 
        'class_trib_ibs_cbs_id',
        'preco_venda', 
        'preco_custo',
        'tem_variacao', 
        'produto_balanca', 
        'estoque',
        'estoque_minimo',
        'ativo',
        'tributacao_id', 
        'pis_cofins_id', 
        'ipi_id', 
        'preco_atacado', 
        'quantidade_minima_atacado',
        'atacado_tem_prazo', 
        'atacado_data_inicio', 
        'atacado_data_fim',
        'codigo_barras_valido',
    ];

    protected $casts = [
        'tem_variacao' => 'boolean',
        'ativo' => 'boolean',
        'preco_venda' => 'decimal:2',
        'preco_custo' => 'decimal:2',
        'produto_balanca' => 'boolean',
        'codigo_barras_valido' => 'boolean',
        'estoque' => 'float',
        'estoque_minimo' => 'float',
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

    public function classificacaoTributaria(): BelongsTo
    {
        return $this->belongsTo(ClassificacaoTributaria::class, 'class_trib_ibs_cbs_id');
    }

    public function pisCofins(): BelongsTo
    {
        return $this->belongsTo(ClassificacaoPisCofins::class, 'pis_cofins_id');
    }

    public function ipi(): BelongsTo
    {
        return $this->belongsTo(ClassificacaoIpi::class, 'ipi_id');
    }

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
    public function getEstoqueTotalAttribute(): float
    {
        return (float) ($this->tem_variacao
            ? $this->variantes()->sum('estoque')
            : $this->estoque);
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