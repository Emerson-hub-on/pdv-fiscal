<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

// Roda uma vez em cada computador de caixa novo. Nunca substitui um banco existente:
// ele pode ter vendas que ainda não subiram para o servidor.
class CaixaInstalarBanco extends Command
{
    protected $signature = 'caixa:instalar-banco';

    protected $description = 'Cria o banco do caixa (database/local.sqlite) a partir do banco base vazio';

    public function handle(): int
    {
        $base = CaixaGerarBase::arquivoBase();
        $destino = config('database.connections.sqlite_local.database');

        if (is_file($destino) && filesize($destino) > 0) {
            $this->error("Este caixa já tem banco em {$destino}. Ele pode ter vendas que ainda não subiram, por isso não foi substituído.");
            $this->line('Para atualizar a estrutura: php artisan migrate --database=sqlite_local --path=database/migrations/local --force');

            return self::FAILURE;
        }

        if (!is_file($base)) {
            $this->error("Banco base não encontrado em {$base}. Gere no computador do desenvolvedor com php artisan caixa:gerar-base.");

            return self::FAILURE;
        }

        copy($base, $destino);

        // Se o ZIP trouxe migrations mais novas que o banco base, aplica só elas
        Artisan::call('migrate', [
            '--database' => 'sqlite_local',
            '--path' => 'database/migrations/local',
            '--force' => true,
        ]);

        $this->info("Banco do caixa criado em {$destino}.");
        $this->line('Agora entre como operador e clique em Atualizar Caixa para trazer os dados do servidor.');

        return self::SUCCESS;
    }
}
