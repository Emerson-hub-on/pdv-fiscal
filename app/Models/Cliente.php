<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $fillable = [
        'tipo_pessoa',
        'nome',
        'nome_fantasia',
        'cpf_cnpj',
        'indicador_ie',
        'ie',
        'email',
        'telefone',
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'municipio',
        'cod_municipio',
        'uf',
        'ativo',
        'limite_credito_ativo',
        'limite_credito',
        'limite_credito_dias',
        'limite_credito_observacao',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'limite_credito_ativo' => 'boolean',
        'limite_credito' => 'decimal:2',
    ];

    /** Contas a receber geradas por vendas no convênio (fiado). */
    public function contasConvenio(): HasMany
    {
        return $this->hasMany(ContaReceber::class)->where('origem', 'convenio');
    }

    public function historicoLimiteCredito(): HasMany
    {
        return $this->hasMany(LimiteCreditoHistorico::class)->latest('id');
    }

    /** Tem limite de convênio concedido (ativo e maior que zero). */
    public function temConvenio(): bool
    {
        return $this->limite_credito_ativo && (float) $this->limite_credito > 0;
    }

    public function vendas(): HasMany
    {
        return $this->hasMany(Venda::class);
    }

    /**
     * CPF/CNPJ formatado pra exibicao (000.000.000-00 ou 00.000.000/0000-00)
     */
    public function getCpfCnpjFormatadoAttribute(): string
    {
        $doc = $this->cpf_cnpj;

        if (strlen($doc) === 11) {
            return substr($doc, 0, 3) . '.' . substr($doc, 3, 3) . '.' . substr($doc, 6, 3) . '-' . substr($doc, 9, 2);
        }

        return substr($doc, 0, 2) . '.' . substr($doc, 2, 3) . '.' . substr($doc, 5, 3) . '/' . substr($doc, 8, 4) . '-' . substr($doc, 12, 2);
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }
}
