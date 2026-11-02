<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Roda na máquina do desenvolvedor, depois de criar ou alterar migrations do caixa.
// Gera o banco do caixa vazio (só estrutura); os dados chegam pela sincronização.
class CaixaGerarBase extends Command
{
    protected $signature = 'caixa:gerar-base';

    protected $description = 'Gera database/local-base.sqlite: o banco do caixa vazio, só com a estrutura';

    public function handle(): int
    {
        $base = self::arquivoBase();

        if (is_file($base)) {
            unlink($base);
        }
        touch($base);

        // As migrations do caixa usam a conexão sqlite_local: aponta para o arquivo novo só neste comando
        config(['database.connections.sqlite_local.database' => $base]);
        DB::purge('sqlite_local');

        $codigo = Artisan::call('migrate', [
            '--database' => 'sqlite_local',
            '--path' => 'database/migrations/local',
            '--force' => true,
        ]);

        if ($codigo !== self::SUCCESS) {
            $this->error(trim(Artisan::output()));

            return self::FAILURE;
        }

        $conexao = DB::connection('sqlite_local');
        $conexao->statement('VACUUM');
        $tabelas = $conexao->table('sqlite_master')->where('type', 'table')->where('name', 'not like', 'sqlite_%')->count();
        $migrations = $conexao->table('migrations')->count();
        DB::purge('sqlite_local');

        $this->info("Banco base gerado em {$base}: {$tabelas} tabelas, {$migrations} migrations registradas.");
        $this->line('Leve-o no ZIP do sistema. No caixa novo: php artisan caixa:instalar-banco');

        return self::SUCCESS;
    }

    public static function arquivoBase(): string
    {
        return database_path('local-base.sqlite');
    }
}
