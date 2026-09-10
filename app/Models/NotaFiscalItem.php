<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaFiscalItem extends Model
{
    protected $table = 'nota_fiscal_itens';

    protected $fillable = [
        'nota_fiscal_id',
        'produto_id',
        'cfop',
        'ncm_id',
        'cest_id',
        'class_trib_ibs_cbs_id',
        'tributacao_id',
        'pis_cofins_id',
        'ipi_id',
        'quantidade',
        'valor_unitario',
        'valor_desconto',
        'valor_total',
    ];

    protected $casts = [
        'quantidade' => 'decimal:3',
        'valor_unitario' => 'decimal:4',
        'valor_desconto' => 'decimal:2',
        'valor_total' => 'decimal:2',
    ];

    public function notaFiscal(): BelongsTo
    {
        return $this->belongsTo(NotaFiscal::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
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
}