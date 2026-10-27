<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Veiculo extends Model
{
    protected $table = 'veiculos';

    public const TRACAO  = 'tracao';
    public const REBOQUE = 'reboque';

    /** tpRod do MDF-e (confirmar com o XSD vigente antes de gerar o XML) */
    public const TIPOS_RODADO = [
        '01' => 'Truck',
        '02' => 'Toco',
        '03' => 'Cavalo mecânico',
        '04' => 'VAN',
        '05' => 'Utilitário',
        '06' => 'Outros',
    ];

    /** tpCar do MDF-e (confirmar com o XSD vigente antes de gerar o XML) */
    public const TIPOS_CARROCERIA = [
        '00' => 'Não aplicável',
        '01' => 'Aberta',
        '02' => 'Fechada/Baú',
        '03' => 'Graneleira',
        '04' => 'Porta container',
        '05' => 'Sider',
    ];

    protected $fillable = [
        'transportador_id', 'tipo', 'placa', 'uf', 'rntrc', 'ativo',
        'tara_kg', 'capacidade_kg', 'capacidade_m3', 'tipo_rodado', 'tipo_carroceria',
    ];

    protected $casts = [
        'ativo'         => 'boolean',
        'tara_kg'       => 'integer',
        'capacidade_kg' => 'integer',
        'capacidade_m3' => 'integer',
    ];

    protected $appends = ['placa_formatada'];

    public function transportador(): BelongsTo
    {
        return $this->belongsTo(Transportador::class);
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    public function scopeTracao($query)
    {
        return $query->where('tipo', self::TRACAO);
    }

    public function scopeReboques($query)
    {
        return $query->where('tipo', self::REBOQUE);
    }

    public function setPlacaAttribute($valor): void
    {
        $this->attributes['placa'] = self::normalizarPlaca($valor);
    }

    /** "abc-1d23" => "ABC1D23" */
    public static function normalizarPlaca(?string $valor): string
    {
        return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $valor));
    }

    /** Placa antiga sai com hífen (ABC-1234); Mercosul fica como está (ABC1D23). */
    public function getPlacaFormatadaAttribute(): string
    {
        $placa = (string) $this->placa;

        return preg_match('/^[A-Z]{3}\d{4}$/', $placa)
            ? substr($placa, 0, 3) . '-' . substr($placa, 3)
            : $placa;
    }
}