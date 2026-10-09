<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fornecedor extends Model
{
    protected $table = 'fornecedores';

    protected $fillable = [
        'tipo_pessoa', 'nome', 'nome_fantasia', 'cpf_cnpj',
        'indicador_ie', 'ie',
        'email', 'telefone',
        'cep', 'logradouro', 'numero', 'complemento', 'bairro',
        'municipio', 'cod_municipio', 'uf',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function entradas()
    {
        return $this->hasMany(EntradaNota::class);
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    /** Nome fantasia quando existir, senão o nome/razão social. */
    public function getNomeExibicaoAttribute(): string
    {
        return $this->nome_fantasia ?: $this->nome;
    }

    public function getCpfCnpjFormatadoAttribute(): string
    {
        $d = (string) $this->cpf_cnpj;

        return strlen($d) === 14
            ? preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d)
            : preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d);
    }

    /** Mantido porque as telas da entrada de nota já usam este nome. */
    public function getDocumentoFormatadoAttribute(): string
    {
        return $this->cpf_cnpj_formatado;
    }
}