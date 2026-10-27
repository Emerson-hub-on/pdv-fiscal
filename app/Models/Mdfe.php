<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mdfe extends Model
{
    protected $table = 'mdfes';

    public const STATUS_RASCUNHO  = 'rascunho';
    public const STATUS_EMITIDO   = 'emitido';
    public const STATUS_ENCERRADO = 'encerrado';
    public const STATUS_CANCELADO = 'cancelado';

    /** tpEmit */
    public const TIPOS_EMITENTE = [
        1 => 'Prestador de serviço de transporte',
        2 => 'Transportador de carga própria',
        3 => 'Prestador de serviço de transporte que emitirá CT-e globalizado',
    ];

    /** tpTransp (opcional) */
    public const TIPOS_TRANSPORTADOR = [
        1 => 'ETC',
        2 => 'TAC',
        3 => 'CTC',
    ];

    protected $fillable = [
        'operador_id',
        'tipo_emitente',
        'tipo_transportador',
        'uf_inicio',
        'uf_fim',
        'inicio_viagem',
        'rntrc',
        'informacoes_adicionais',
        'informacoes_fisco',
    ];

    protected $casts = [
        'inicio_viagem'  => 'datetime',
        'emitido_em'     => 'datetime',
        'encerrado_em'   => 'datetime',
        'cancelado_em'   => 'datetime',
        'valor_carga'    => 'decimal:2',
        'peso_carga_kg'  => 'decimal:3',
    ];

    public function operador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operador_id');
    }

    public function serieMdfe(): BelongsTo
    {
        return $this->belongsTo(SerieMdfe::class, 'serie_mdfe_id');
    }

    public function percursos(): HasMany
    {
        return $this->hasMany(MdfePercurso::class)->orderBy('ordem');
    }

    public function municipios(): HasMany
    {
        return $this->hasMany(MdfeMunicipio::class)->orderBy('ordem');
    }

    public function municipiosCarregamento(): HasMany
    {
        return $this->municipios()->where('tipo', MdfeMunicipio::CARREGAMENTO);
    }

    public function municipiosDescarregamento(): HasMany
    {
        return $this->municipios()->where('tipo', MdfeMunicipio::DESCARREGAMENTO);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(MdfeDocumento::class);
    }

    public function veiculos(): HasMany
    {
        return $this->hasMany(MdfeVeiculo::class)->orderBy('ordem');
    }

    public function condutores(): HasMany
    {
        return $this->hasMany(MdfeCondutor::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(MdfeEvento::class);
    }

    public function scopeRascunhos($query)
    {
        return $query->where('status', self::STATUS_RASCUNHO);
    }

    public function scopeAbertos($query)
    {
        return $query->where('status', self::STATUS_EMITIDO);
    }

    /** Recalcula quantidade de NF-e, valor e peso da carga com base nos documentos vinculados. */
    public function recalcularTotais(): void
    {
        $documentos = $this->documentos()->get(['tipo', 'valor', 'peso_bruto_kg']);

        $this->quantidade_nfe = $documentos->where('tipo', 'nfe')->count();
        $this->valor_carga    = $documentos->sum('valor');
        $this->peso_carga_kg  = $documentos->sum('peso_bruto_kg');
        $this->save();
    }
}
