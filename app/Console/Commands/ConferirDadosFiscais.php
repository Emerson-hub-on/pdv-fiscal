<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use NFePHP\Common\Certificate;

class ConferirDadosFiscais extends Command
{
    protected $signature = 'nfce:conferir-dados';

    protected $description = 'Confere se o SQLite local tem o necessário para emitir NFC-e sem o servidor';

    public function handle(): int
    {
        $db = DB::connection('sqlite_local');
        $problemas = [];

        // Empresa e certificado
        $empresa = $db->table('empresa_cache')->first();

        if (!$empresa) {
            $problemas[] = ['empresa', 'Empresa não sincronizada'];
        } elseif (!$empresa->certificado || !$empresa->certificado_senha) {
            $problemas[] = ['empresa', 'Certificado ou senha ausente'];
        } else {
            try {
                $certificado = Certificate::readPfx(
                    base64_decode(Crypt::decryptString($empresa->certificado)),
                    Crypt::decryptString($empresa->certificado_senha)
                );

                if ($certificado->isExpired()) {
                    $problemas[] = ['empresa', 'Certificado vencido'];
                } else {
                    $this->info('Certificado válido até ' . $certificado->getValidTo()->format('d/m/Y'));
                }
            } catch (\Throwable $e) {
                $problemas[] = ['empresa', 'Certificado ilegível: ' . $e->getMessage()];
            }
        }

        // PDVs
        foreach ($db->table('pdvs_cache')->where('ativo', true)->get() as $pdv) {
            if (!$pdv->csc || !$pdv->csc_id) {
                $problemas[] = ["PDV {$pdv->nome}", 'CSC ou ID do CSC ausente'];
            }

            if (!$pdv->serie_nfce) {
                $problemas[] = ["PDV {$pdv->nome}", 'Série da NFC-e ausente'];
            }
        }

        // Produtos ativos: o que cada regime exige para montar o XML
        $crt = $empresa->crt ?? 1;
        $ativos = fn () => $db->table('produtos_cache')->where('ativo', true);

        $verificacoes = [
            'sem NCM'                        => fn ($q) => $q->where(fn ($w) => $w->whereNull('ncm')->orWhere('ncm', '')),
            'sem CFOP'                       => fn ($q) => $q->where(fn ($w) => $w->whereNull('cfop_padrao')->orWhere('cfop_padrao', '')),
            ($crt <= 2 ? 'sem CSOSN' : 'sem CST de ICMS') => fn ($q) => $crt <= 2
                ? $q->where(fn ($w) => $w->whereNull('csosn')->orWhere('csosn', ''))
                : $q->where(fn ($w) => $w->whereNull('cst_icms')->orWhere('cst_icms', '')),
        ];

        if ($crt == 3) {
            $verificacoes['sem classificação IBS/CBS'] = fn ($q) => $q->whereNull('class_trib_ibs_cbs');
        }

        foreach ($verificacoes as $descricao => $filtro) {
            $afetados = $filtro($ativos())->limit(5)->pluck('nome');

            if ($afetados->isNotEmpty()) {
                $total = $filtro($ativos())->count();
                $problemas[] = ["Produtos {$descricao}", "{$total} produto(s), por exemplo: " . $afetados->implode(', ')];
            }
        }

        if ($problemas) {
            $this->table(['Item', 'Problema'], $problemas);
            $this->error(count($problemas) . ' ponto(s) a corrigir.');

            return self::FAILURE;
        }

        $this->info('Tudo certo: o caixa tem os dados fiscais para emitir sem o servidor.');

        return self::SUCCESS;
    }
}