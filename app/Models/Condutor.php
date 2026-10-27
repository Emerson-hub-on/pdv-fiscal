<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Condutor extends Model
{
    protected $table = 'condutores';

    protected $fillable = ['nome', 'cpf', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];

    protected $appends = ['cpf_formatado'];

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    public function setCpfAttribute($valor): void
    {
        $this->attributes['cpf'] = self::normalizarCpf($valor);
    }

    /** "123.456.789-09" => "12345678909" */
    public static function normalizarCpf(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor);
    }

    public function getCpfFormatadoAttribute(): string
    {
        return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', (string) $this->cpf);
    }

    /** Reaproveita a validação de CPF que já existe em Transportador (só aceita 11 dígitos). */
    public static function cpfValido(string $cpf): bool
    {
        return strlen($cpf) === 11 && Transportador::documentoValido($cpf);
    }
}
