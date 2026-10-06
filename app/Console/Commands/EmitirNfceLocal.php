<?php

namespace App\Console\Commands;

use App\Services\EmissorLocalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EmitirNfceLocal extends Command
{
    protected $signature = 'nfce:emitir-local {uuid : uuid da venda em vendas_pendentes}';

    protected $description = 'Emite (ou reenvia) a NFC-e de uma venda do caixa SEM usar o servidor. Somente homologação.';

    public function handle(): int
    {
        $empresa = DB::connection('sqlite_local')->table('empresa_cache')->first();

        if (!$empresa) {
            $this->error('Empresa não sincronizada. Rode a sincronização antes.');

            return self::FAILURE;
        }

        // Trava de segurança desta etapa: não emite em produção
        if ((int) $empresa->ambiente !== 2) {
            $this->error('A empresa está em PRODUÇÃO. Este comando de teste só roda em homologação (ambiente 2).');

            return self::FAILURE;
        }

        $uuid = $this->argument('uuid');

        try {
            $resultado = (new EmissorLocalService())->emitirLocal($uuid);

            $this->info("NFC-e autorizada. Chave: {$resultado['chave']} | Protocolo: {$resultado['protocolo']}");
        } catch (\Throwable $e) {
            // Inclui o caso normal da contingência (a emissão em tpEmis 9 também termina em exceção)
            $this->warn($e->getMessage());
        }

        $venda = DB::connection('sqlite_local')->table('vendas_pendentes')->where('uuid', $uuid)->first();

        if ($venda) {
            $this->table(
                ['status_fiscal', 'número', 'série', 'tpEmis', 'chave', 'motivo'],
                [[$venda->status_fiscal, $venda->numero_nfce, $venda->serie_nfce, $venda->tp_emis, $venda->chave_nfe, $venda->motivo_rejeicao]]
            );
            $this->line('XML: ' . ($venda->ultimo_arquivo_xml ?: '-'));
        }

        return $venda && $venda->status_fiscal === 'emitida' ? self::SUCCESS : self::FAILURE;
    }
}