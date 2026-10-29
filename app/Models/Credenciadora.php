<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Credenciadora extends Model
{
    protected $table = 'credenciadoras';

    /**
     * Meios de pagamento que passam por banco/instituição de pagamento e entram no registro 1601.
     * Dinheiro, cheque e crediário próprio não entram (o dinheiro não passa por instituição).
     */
    public const MEIOS_ELETRONICOS = ['03', '04', '05', '10', '11', '12', '13', '15', '16', '17', '18', '20', '23', '24'];

    /** PIX tem três códigos na NF-e; a venda do caixa só sabe que foi "pix" */
    public const MEIOS_PIX = ['17', '20', '23'];

    /** venda_pagamentos.forma_pagamento (caixa) => código do meio */
    public const MEIO_DO_CAIXA = [
        'dinheiro' => '01',
        'credito'  => '03',
        'debito'   => '04',
        'pix'      => '17',
    ];

    protected $fillable = ['nome', 'cnpj', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'municipio', 'cod_municipio', 'uf', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];

    protected $appends = ['cnpj_formatado'];

    public function meios(): HasMany
    {
        return $this->hasMany(CredenciadoraMeio::class);
    }

    public function scopeAtivas($query)
    {
        return $query->where('ativo', true);
    }

    public function setCnpjAttribute($valor): void
    {
        $this->attributes['cnpj'] = Transportador::normalizarDocumento($valor);
    }

    public function getCnpjFormatadoAttribute(): string
    {
        $d = (string) $this->cnpj;

        return strlen($d) === 14 ? preg_replace('/^(.{2})(.{3})(.{3})(.{4})(.{2})$/', '$1.$2.$3/$4-$5', $d) : $d;
    }

    /** Meios eletrônicos com o rótulo da tabela da NF-e. */
    public static function opcoesDeMeio(): array
    {
        return array_intersect_key(FormaPagamento::MEIOS, array_flip(self::MEIOS_ELETRONICOS));
    }

    /**
     * Mapa meio => credenciadora (só as ativas). Para PIX, um meio sem credenciadora usa a de
     * qualquer outro código de PIX cadastrado (o caixa não distingue PIX estático de dinâmico).
     */
    public static function mapaPorMeio(): array
    {
        $mapa = CredenciadoraMeio::query()
            ->whereHas('credenciadora', fn ($q) => $q->where('ativo', true))
            ->with('credenciadora')
            ->get()
            ->mapWithKeys(fn ($m) => [$m->meio_pagamento => $m->credenciadora])
            ->all();

        $pix = collect(self::MEIOS_PIX)->map(fn ($c) => $mapa[$c] ?? null)->filter()->first();

        foreach (self::MEIOS_PIX as $codigo) {
            $mapa[$codigo] ??= $pix;
        }

        return array_filter($mapa);
    }
}
