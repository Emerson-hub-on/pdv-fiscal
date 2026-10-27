<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MdfeVeiculo extends Model
{
    protected $table = 'mdfe_veiculos';

    public const TRACAO  = 'tracao';
    public const REBOQUE = 'reboque';

    public $timestamps = false;

    protected $fillable = [
        'mdfe_id', 'papel', 'veiculo_id', 'ordem',
        'placa', 'uf', 'rntrc', 'tara_kg', 'capacidade_kg', 'capacidade_m3',
        'tipo_rodado', 'tipo_carroceria',
        'proprietario_tipo', 'proprietario_documento', 'proprietario_nome',
        'proprietario_ie', 'proprietario_uf', 'proprietario_rntrc',
    ];

    public function mdfe(): BelongsTo
    {
        return $this->belongsTo(Mdfe::class);
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function condutores(): HasMany
    {
        return $this->hasMany(MdfeCondutor::class);
    }
}
