<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    protected $table = 'empresa';

    protected $fillable = [
        'cnpj', 'razao_social', 'nome_fantasia', 'ie', 'im', 'crt',
        'logradouro', 'numero', 'complemento', 'bairro', 'cep',
        'municipio', 'cod_municipio', 'uf',
        'certificado_base64', 'certificado_senha', 'certificado_validade',
        'csc', 'csc_id',
        'serie_nfce', 'numero_atual_nfce', 'serie_nfe', 'numero_atual_nfe',
        'ambiente',
    ];

    protected $hidden = [
        'certificado_base64', 'certificado_senha',
    ];

    protected $casts = [
        'certificado_validade' => 'date',
    ];

    /**
     * Retorna o registro único da empresa (ou cria vazio se não existir ainda).
     * Nunca deve haver mais de 1 linha nessa tabela.
     */
    public static function atual(): self
    {
        return static::first() ?? new static();
    }

    /**
     * Impede a criação de um segundo registro.
     */
    protected static function booted(): void
    {
        static::creating(function () {
            if (static::exists()) {
                throw new \Exception('Já existe um registro de empresa. Edite o existente em vez de criar outro.');
            }
        });
    }
}