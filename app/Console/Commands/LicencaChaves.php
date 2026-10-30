<?php

namespace App\Console\Commands;

use App\Services\LicencaService;
use Illuminate\Console\Command;

// Roda uma única vez, só na máquina de quem emite as licenças.
// A chave privada nunca vai para o cliente: quem a tiver consegue emitir licenças.
class LicencaChaves extends Command
{
    protected $signature = 'licenca:chaves {--chave= : Onde gravar a chave privada (padrão: pasta do usuário\\.cyberly)}';

    protected $description = 'Cria o par de chaves usado para assinar as licenças (só na máquina do desenvolvedor)';

    public function handle(): int
    {
        $arquivo = $this->option('chave') ?: LicencaService::arquivoChavePrivada();

        if (is_file($arquivo)) {
            $this->error("Já existe uma chave privada em {$arquivo}. Apagar essa chave invalida todas as licenças já emitidas.");

            return self::FAILURE;
        }

        $par = sodium_crypto_sign_keypair();

        if (!is_dir(dirname($arquivo))) {
            mkdir(dirname($arquivo), 0700, true);
        }
        file_put_contents($arquivo, base64_encode(sodium_crypto_sign_secretkey($par)));

        $this->info("Chave privada gravada em {$arquivo} (faça backup dela).");
        $this->line('Chave pública (vai em LicencaService::CHAVE_PUBLICA):');
        $this->line(base64_encode(sodium_crypto_sign_publickey($par)));

        return self::SUCCESS;
    }
}
