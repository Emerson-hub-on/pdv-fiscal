<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Servico extends Model
{
    protected $table = 'servicos';

    protected $fillable = [
        'nome', 'descricao', 'valor_padrao',
        'codigo_tributacao_nacional', 'codigo_tributacao_municipal', 'cnae',
        'tributacao_issqn', 'aliquota_iss', 'ativo',
    ];

    protected $casts = [
        'valor_padrao'     => 'decimal:2',
        'aliquota_iss'     => 'decimal:2',
        'tributacao_issqn' => 'integer',
        'ativo'            => 'boolean',
    ];

    /** Tributação do ISSQN (campo da DPS). Confirme os valores na homologação. */
    public const TRIBUTACAO_ISSQN = [
        1 => 'Operação tributável (ISS devido)',
        2 => 'Imunidade',
        3 => 'Exportação de serviço',
        4 => 'Não incidência',
    ];

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    /** "140101" => "14.01.01" */
    public function codigoTributacaoFormatado(): ?string
    {
        $d = preg_replace('/\D/', '', (string) $this->codigo_tributacao_nacional);

        return strlen($d) === 6 ? substr($d, 0, 2) . '.' . substr($d, 2, 2) . '.' . substr($d, 4, 2) : ($d ?: null);
    }

    /** Item/subitem da LC 116 (os 4 primeiros dígitos): "140101" => "14.01" */
    public function itemListaServico(): ?string
    {
        $d = preg_replace('/\D/', '', (string) $this->codigo_tributacao_nacional);

        return strlen($d) >= 4 ? substr($d, 0, 2) . '.' . substr($d, 2, 2) : null;
    }

    /** "9511800" => "9511-8/00" */
    public function cnaeFormatado(): ?string
    {
        $d = preg_replace('/\D/', '', (string) $this->cnae);

        return strlen($d) === 7 ? substr($d, 0, 4) . '-' . substr($d, 4, 1) . '/' . substr($d, 5, 2) : ($d ?: null);
    }

    public function tributacaoIssqnTexto(): string
    {
        return self::TRIBUTACAO_ISSQN[$this->tributacao_issqn] ?? '—';
    }
}
