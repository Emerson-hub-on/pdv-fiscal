<?php

namespace App\Console\Commands;

use App\Services\LicencaService;
use Carbon\Carbon;
use Illuminate\Console\Command;

// Só funciona onde está a chave privada (máquina do desenvolvedor)
class LicencaGerar extends Command
{
    protected $signature = 'licenca:gerar
        {cnpj : CNPJ da empresa do cliente}
        {valida_ate : Último dia de uso liberado (AAAA-MM-DD)}
        {--chave= : Arquivo da chave privada (padrão: pasta do usuário\\.cyberly)}';

    protected $description = 'Gera o código de licença que o cliente cola na tela Licença do sistema';

    public function handle(): int
    {
        $cnpj = preg_replace('/\D/', '', $this->argument('cnpj'));
        if (strlen($cnpj) !== 14) {
            $this->error('CNPJ deve ter 14 dígitos.');

            return self::FAILURE;
        }

        try {
            $validaAte = Carbon::createFromFormat('!Y-m-d', $this->argument('valida_ate'));
        } catch (\Throwable) {
            $this->error('Data inválida: use AAAA-MM-DD.');

            return self::FAILURE;
        }

        $arquivo = $this->option('chave') ?: LicencaService::arquivoChavePrivada();
        if (!is_file($arquivo)) {
            $this->error("Chave privada não encontrada em {$arquivo}.");

            return self::FAILURE;
        }

        $codigo = LicencaService::assinar([
            'cnpj'       => $cnpj,
            'valida_ate' => $validaAte->format('Y-m-d'),
            'emitida_em' => now()->format('Y-m-d'),
        ], base64_decode(trim(file_get_contents($arquivo))));

        $this->info("Licença do CNPJ {$cnpj} válida até {$validaAte->format('d/m/Y')} "
            . '(bloqueia depois de ' . LicencaService::TOLERANCIA_DIAS . ' dias de tolerância):');
        $this->line($codigo);

        return self::SUCCESS;
    }
}
