<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaFiscalItem extends Model
{
    protected $table = 'nota_fiscal_itens';

    protected $fillable = [
        'nota_fiscal_id', 'produto_id', 'produto_variante_id','descricao', 
        'ncm_id', 'cest_id','class_trib_ibs_cbs_id', 'tributacao_id', 
        'pis_cofins_id', 'ipi_id','quantidade', 'valor_unitario', 
        'valor_desconto', 'valor_total','ref_chave_acesso', 'ref_nitem',
        'bc_icms_manual', 'valor_icms_manual', 'aliquota_icms_manual', 
        'valor_ipi_manual', 'aliquota_ipi_manual', 'valor_outras_despesas',
        'valor_frete',
    ];

    protected $casts = [
        'quantidade'            => 'decimal:3',
        'valor_unitario'        => 'decimal:4',
        'valor_desconto'        => 'decimal:2',
        'valor_total'           => 'decimal:2',
        'bc_icms_manual'        => 'decimal:2',
        'valor_icms_manual'     => 'decimal:2',
        'aliquota_icms_manual'  => 'decimal:2',
        'valor_ipi_manual'      => 'decimal:2',
        'aliquota_ipi_manual'   => 'decimal:2',
        'valor_outras_despesas' => 'decimal:2',
        'valor_frete'           => 'decimal:2',
    ];


    public function cfopEfetivo(NotaFiscal $nota): string
    {
        $cfopNota    = $nota->cfopSaida->codigo;
        $cfopProduto = $this->tributacao?->cfop;

        if (!$cfopProduto || !in_array($cfopNota, config('fiscal.cfops_venda_por_item', []), true)) {
            return $cfopNota;
        }

        // Nota interestadual (CFOP do cabeçalho começa com 6)
        if (str_starts_with($cfopNota, '6')) {
            return config("fiscal.cfop_interestadual.{$cfopProduto}")
                ?? '6' . substr($cfopProduto, 1);
        }

        return $cfopProduto;
    }

    public function getBaseImpostosAttribute(): float
    {
        return ((float) $this->valor_unitario * (float) $this->quantidade)
            + (float) $this->valor_outras_despesas
            + (float) $this->valor_frete;
    }

    public function notaFiscal(): BelongsTo
    {
        return $this->belongsTo(NotaFiscal::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProdutoVariante::class, 'produto_variante_id');
    }

    public function ncm(): BelongsTo
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

    public function tributacao(): BelongsTo
    {
        return $this->belongsTo(Tributacao::class);
    }

    public function pisCofins(): BelongsTo
    {
        return $this->belongsTo(ClassificacaoPisCofins::class, 'pis_cofins_id');
    }

    public function ipi(): BelongsTo
    {
        return $this->belongsTo(ClassificacaoIpi::class, 'ipi_id');
    }

    public function getQuantidadeFormatadaAttribute(): string
    {
        return rtrim(rtrim(number_format($this->quantidade, 3, ',', '.'), '0'), ',');
    }
}