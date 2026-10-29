<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpedConfiguracao extends Model
{
    protected $table = 'sped_configuracoes';

    public const ATIV_INDUSTRIAL = 0;
    public const ATIV_OUTROS     = 1;

    public const PERFIS = [
        'A' => 'Perfil A',
        'B' => 'Perfil B',
        'C' => 'Perfil C',
    ];

    /** Registro 0002 — classificação do estabelecimento industrial ou equiparado */
    public const CLASSIFICACOES_INDUSTRIAIS = [
        '00' => 'Industrial - Transformação',
        '01' => 'Industrial - Beneficiamento',
        '02' => 'Industrial - Montagem',
        '03' => 'Industrial - Acondicionamento ou reacondicionamento',
        '04' => 'Industrial - Renovação ou recondicionamento',
        '05' => 'Equiparado a industrial - Por opção',
        '06' => 'Equiparado a industrial - Importação direta',
        '07' => 'Equiparado a industrial - Por lei específica',
        '08' => 'Equiparado a industrial - Não enquadrado nos códigos 05, 06 ou 07',
        '09' => 'Outros',
    ];

    protected $fillable = [
        'ind_ativ', 'ind_perfil', 'clas_estab_ind', 'cod_receita_icms',
        'empresa_fone', 'empresa_email',
        'contador_nome', 'contador_cpf', 'contador_crc', 'contador_cnpj',
        'contador_cep', 'contador_endereco', 'contador_numero', 'contador_complemento',
        'contador_bairro', 'contador_municipio', 'contador_cod_municipio', 'contador_uf',
        'contador_fone', 'contador_email',
    ];

    protected $casts = ['ind_ativ' => 'integer'];

    /** Registro único: devolve o existente ou um novo (ainda não gravado). */
    public static function atual(): self
    {
        return static::first() ?? new static(['ind_ativ' => self::ATIV_OUTROS, 'ind_perfil' => 'A']);
    }

    public function isIndustrial(): bool
    {
        return $this->ind_ativ === self::ATIV_INDUSTRIAL;
    }

    /** Tem o mínimo que o registro 0100 exige? */
    public function contadorCompleto(): bool
    {
        foreach (['contador_nome', 'contador_cpf', 'contador_crc', 'contador_cep', 'contador_endereco',
                  'contador_bairro', 'contador_cod_municipio', 'contador_email'] as $campo) {
            if (blank($this->{$campo})) {
                return false;
            }
        }

        return true;
    }
}