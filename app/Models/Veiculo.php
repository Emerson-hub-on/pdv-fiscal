<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Veiculo extends Model
{
    protected $table = 'veiculos';

    protected $fillable = ['transportador_id', 'placa', 'uf', 'rntrc', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];

    protected $appends = ['placa_formatada'];

    public function transportador(): BelongsTo
    {
        return $this->belongsTo(Transportador::class);
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
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