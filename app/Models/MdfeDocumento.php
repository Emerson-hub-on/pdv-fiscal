<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MdfeDocumento extends Model
{
    protected $table = 'mdfe_documentos';

    public $timestamps = false;

    protected $fillable = [
        'mdfe_id', 'mdfe_municipio_id', 'tipo', 'nota_fiscal_id',
        'chave_acesso', 'valor', 'peso_bruto_kg',
    ];

    protected $casts = [
        'valor'         => 'decimal:2',
        'peso_bruto_kg' => 'decimal:3',
    ];

    public function mdfe(): BelongsTo
    {
        return $this->belongsTo(Mdfe::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(MdfeMunicipio::class, 'mdfe_municipio_id');
    }

    public function notaFiscal(): BelongsTo
    {
        return $this->belongsTo(NotaFiscal::class);
    }
}
