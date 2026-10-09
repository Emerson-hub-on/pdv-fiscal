<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fornecedor extends Model
{
    protected $table = 'fornecedores';

    protected $fillable = [
        'cnpj_cpf', 'razao_social', 'nome_fantasia', 'ie',
        'logradouro', 'numero', 'complemento', 'bairro', 'cep',
        'municipio', 'cod_municipio', 'uf',
        'telefone', 'email', 'ativo',
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

    public function getNomeExibicaoAttribute(): string
    {
        return $this->nome_fantasia ?: $this->razao_social;
    }

    public function getDocumentoFormatadoAttribute(): string
    {
        $d = $this->cnpj_cpf;

        return strlen($d) === 14
            ? preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d)
            : preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d);
    }
}
