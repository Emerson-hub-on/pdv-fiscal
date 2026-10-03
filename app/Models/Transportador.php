<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transportador extends Model
{
    protected $table = 'transportadores';

    protected $fillable = [
        'tipo_pessoa', 'documento', 'nome', 'ie',
        'logradouro', 'numero', 'bairro', 'municipio', 'uf', 'ativo',
    ];

    protected $casts = ['ativo' => 'boolean'];

    protected $appends = ['documento_formatado'];

    public const UFS = [
        'AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA',
        'PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO',
    ];

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    public function setDocumentoAttribute($valor): void
    {
        $this->attributes['documento'] = self::normalizarDocumento($valor);
    }

    /** Tira máscara e deixa maiúsculo: "12.ABC.345/01DE-35" => "12ABC34501DE35". */
    public static function normalizarDocumento(?string $valor): string
    {
        return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $valor));
    }

    public function getDocumentoFormatadoAttribute(): string
    {
        $d = (string) $this->documento;

        if (strlen($d) === 11) {
            return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $d);
        }

        if (strlen($d) === 14) {
            return preg_replace('/^(.{2})(.{3})(.{3})(.{4})(.{2})$/', '$1.$2.$3/$4-$5', $d);
        }

        return $d;
    }

    /** Texto do xEnder do XML (limite de 60 caracteres): logradouro, número, bairro. */
    public function getEnderecoNfeAttribute(): string
    {
        return mb_substr(implode(', ', array_filter([$this->logradouro, $this->numero, $this->bairro])), 0, 60);
    }

    /** CPF (11 dígitos) ou CNPJ (14 posições; as 12 primeiras podem ter letras). */
    public static function documentoValido(string $doc): bool
    {
        if (preg_match('/^\d{11}$/', $doc)) {
            if (preg_match('/^(\d)\1{10}$/', $doc)) {
                return false;
            }

            for ($t = 9; $t <= 10; $t++) {
                $soma = 0;
                for ($i = 0; $i < $t; $i++) {
                    $soma += (int) $doc[$i] * (($t + 1) - $i);
                }
                if ((int) $doc[$t] !== (($soma * 10) % 11) % 10) {
                    return false;
                }
            }

            return true;
        }

        if (preg_match('/^[0-9A-Z]{12}\d{2}$/', $doc)) {
            if (preg_match('/^(.)\1{13}$/', $doc)) {
                return false;
            }

            $pesos = [[5,4,3,2,9,8,7,6,5,4,3,2], [6,5,4,3,2,9,8,7,6,5,4,3,2]];

            foreach ($pesos as $k => $conjunto) {
                $soma = 0;
                foreach ($conjunto as $i => $peso) {
                    $soma += (ord($doc[$i]) - 48) * $peso; // dígito = valor; letra A = 17, B = 18...
                }
                $resto = $soma % 11;
                $dv = $resto < 2 ? 0 : 11 - $resto;

                if ((int) $doc[12 + $k] !== $dv) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }
}