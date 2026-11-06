<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Formas de pagamento do caixa: fonte única para a tela de pagamento, a NFC-e, o fechamento, o SPED e os relatórios.
 *
 * - 'caixa': lê o SQLite do caixa (formas_pagamento_cache, trazido no Atualizar Caixa) — funciona offline;
 *   se o cache estiver vazio, cai para o servidor.
 * - 'servidor': lê o MySQL (formas_pagamento_caixa), cadastrado no painel.
 * - Em último caso (tabelas ainda não criadas), usa as 6 formas padrão abaixo.
 *
 * Cada item: codigo, descricao, meio_pagamento (tPag), tipo, permite_troco, ativo, ordem.
 */
class FormasCaixa
{
    /** As formas que existiam fixas no código (viram as "do sistema" do cadastro). */
    public const PADRAO = [
        ['codigo' => 'dinheiro',     'descricao' => 'Dinheiro',                'meio_pagamento' => '01', 'tipo' => 'dinheiro',     'permite_troco' => true],
        ['codigo' => 'pix',          'descricao' => 'PIX',                     'meio_pagamento' => '17', 'tipo' => 'pix',          'permite_troco' => false],
        ['codigo' => 'credito',      'descricao' => 'Cartão de Crédito',       'meio_pagamento' => '03', 'tipo' => 'credito',      'permite_troco' => false],
        ['codigo' => 'debito',       'descricao' => 'Cartão de Débito',        'meio_pagamento' => '04', 'tipo' => 'debito',       'permite_troco' => false],
        ['codigo' => 'credito_loja', 'descricao' => 'Crédito em loja (troca)', 'meio_pagamento' => '21', 'tipo' => 'credito_loja', 'permite_troco' => false],
        ['codigo' => 'convenio',     'descricao' => 'Convênio (fiado)',        'meio_pagamento' => '05', 'tipo' => 'convenio',     'permite_troco' => false],
    ];

    /** Meios de pagamento da SEFAZ (tPag) que podem ser escolhidos numa forma nova. 05 e 21 são das formas do sistema. */
    public const MEIOS = [
        '01' => 'Dinheiro',
        '02' => 'Cheque',
        '03' => 'Cartão de Crédito',
        '04' => 'Cartão de Débito',
        '10' => 'Vale Alimentação',
        '11' => 'Vale Refeição',
        '12' => 'Vale Presente',
        '13' => 'Vale Combustível',
        '15' => 'Boleto Bancário',
        '16' => 'Depósito Bancário',
        '17' => 'PIX dinâmico',
        '18' => 'Transferência bancária / Carteira digital',
        '19' => 'Programa de fidelidade / Cashback',
        '20' => 'PIX estático',
        '99' => 'Outros',
    ];

    /** Pagamentos eletrônicos com integração ao TEF/POS (tpIntegra na NFC-e). */
    public const MEIOS_ELETRONICOS = ['03', '04', '17'];

    /** Tipos com tela própria no caixa: não podem ser criados no cadastro, só existem como formas do sistema. */
    public const TIPOS_ESPECIAIS = ['credito_loja', 'convenio'];

    private static array $memo = [];

    public static function lista(string $fonte = 'servidor'): Collection
    {
        return self::$memo[$fonte] ??= self::carregar($fonte);
    }

    /** Esquece o que foi lido (depois de cadastrar/sincronizar). */
    public static function limpar(): void
    {
        self::$memo = [];
    }

    private static function carregar(string $fonte): Collection
    {
        if ($fonte === 'caixa') {
            try {
                $local = DB::connection('sqlite_local');
                if ($local->getSchemaBuilder()->hasTable('formas_pagamento_cache')) {
                    $linhas = $local->table('formas_pagamento_cache')->orderBy('ordem')->orderBy('id')->get();
                    if ($linhas->isNotEmpty()) {
                        return self::normalizar($linhas);
                    }
                }
            } catch (\Throwable $e) {
                // sem SQLite local: segue para o servidor
            }
        }

        try {
            if (Schema::hasTable('formas_pagamento_caixa')) {
                $linhas = DB::table('formas_pagamento_caixa')->orderBy('ordem')->orderBy('id')->get();
                if ($linhas->isNotEmpty()) {
                    return self::normalizar($linhas);
                }
            }
        } catch (\Throwable $e) {
            // servidor fora do ar
        }

        return self::normalizar(collect(self::PADRAO)->map(fn ($f, $i) => (object) ($f + ['ativo' => true, 'ordem' => $i])));
    }

    private static function normalizar(Collection $linhas): Collection
    {
        return $linhas->map(fn ($f) => [
            'codigo'         => (string) $f->codigo,
            'descricao'      => (string) $f->descricao,
            'meio_pagamento' => (string) $f->meio_pagamento,
            'tipo'           => (string) ($f->tipo ?? 'outro'),
            'permite_troco'  => (bool) $f->permite_troco,
            'ativo'          => (bool) $f->ativo,
            'ordem'          => (int) ($f->ordem ?? 0),
        ])->keyBy('codigo');
    }

    /** Formas ativas, na ordem do cadastro. */
    public static function ativas(string $fonte = 'servidor'): Collection
    {
        return self::lista($fonte)->where('ativo', true)->values();
    }

    public static function forma(?string $codigo, string $fonte = 'servidor'): ?array
    {
        return $codigo === null ? null : self::lista($fonte)->get($codigo);
    }

    /** tPag da forma (formas antigas sem cadastro: pelo padrão; sem nada: 99). */
    public static function meio(?string $codigo, string $fonte = 'servidor'): string
    {
        return self::forma($codigo, $fonte)['meio_pagamento']
            ?? collect(self::PADRAO)->firstWhere('codigo', $codigo)['meio_pagamento']
            ?? '99';
    }

    public static function rotulo(?string $codigo, string $fonte = 'servidor'): string
    {
        return self::forma($codigo, $fonte)['descricao']
            ?? collect(self::PADRAO)->firstWhere('codigo', $codigo)['descricao']
            ?? (string) ($codigo ?: '-');
    }

    /** [codigo => descricao] de todas (inclusive inativas, para rotular vendas antigas). */
    public static function rotulos(string $fonte = 'servidor'): array
    {
        return self::lista($fonte)->map(fn ($f) => $f['descricao'])->all();
    }

    /** Códigos cujo dinheiro fica na gaveta (meio 01). */
    public static function codigosDinheiro(string $fonte = 'servidor'): array
    {
        return self::lista($fonte)->where('meio_pagamento', '01')->keys()->all() ?: ['dinheiro'];
    }

    /** Formas para receber a baixa do convênio no caixa: as ativas, menos crédito em loja e o próprio convênio. */
    public static function paraRecebimento(string $fonte = 'caixa'): Collection
    {
        return self::ativas($fonte)->reject(fn ($f) => in_array($f['tipo'], self::TIPOS_ESPECIAIS, true))->values();
    }
}
