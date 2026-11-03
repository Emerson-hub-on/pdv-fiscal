<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

// Roda na máquina do desenvolvedor, antes de montar o ZIP de instalação.
// Gera o banco do servidor pronto para importar: estrutura, dados de referência e migrations, sem movimento.
class ServidorGerarBase extends Command
{
    protected $signature = 'servidor:gerar-base
        {--bin= : Pasta do mysqldump (padrão: o que estiver no PATH, como no Terminal do Laragon)}';

    protected $description = 'Gera database/cyberly-base.sql: o banco do servidor com a estrutura e só os dados de referência';

    // Tabelas de migrations do caixa rodadas por engano no MySQL: não fazem parte do banco do servidor
    private const FORA = ['produtos_cache', 'produto_variantes_cache', 'sync_meta', 'vendas_pendentes'];

    // Dados permanentes, iguais em todo cliente; o resto do banco vai vazio
    private const REFERENCIA = [
        'ncms', 'cests', 'cest_ncm', 'municipios', 'lista_servico_nacional', 'classificacoes_tributarias',
        'classificacoes_pis_cofins', 'classificacoes_ipi', 'cfop_saida', 'cfops_entrada', 'operacoes_entrada',
        'cfop_entrada_conversoes', 'cst_entrada_conversoes', 'unidades', 'tipos_operador', 'formas_pagamento',
        'tipos_conta_pagar', 'tipos_conta_receber', 'tributacoes',
    ];

    public function handle(): int
    {
        $conexao = config('database.connections.mysql');
        $banco = $conexao['database'];

        // Mesmo nome que o Laravel usa: tira todo ".php" (há arquivos terminados em .php.php)
        $arquivos = collect(glob(database_path('migrations/*.php')))
            ->map(fn ($f) => str_replace('.php', '', basename($f)));
        $registradas = DB::table('migrations')->orderBy('id')->pluck('migration');

        $pendentes = $arquivos->diff($registradas);
        if ($pendentes->isNotEmpty()) {
            $this->error('Rode php artisan migrate antes. Pendentes: ' . $pendentes->implode(', '));

            return self::FAILURE;
        }

        $tabelas = collect(DB::select('SHOW TABLES'))
            ->map(fn ($t) => array_values((array) $t)[0])
            ->reject(fn ($t) => in_array($t, self::FORA, true))
            ->values()
            ->all();

        try {
            // Sem DROP TABLE: importar por cima de um banco com dados falha, em vez de apagar tudo
            $estrutura = $this->mysqldump($conexao, ['--no-data', '--skip-add-drop-table', $banco, ...$tabelas]);
            $dados = $this->mysqldump($conexao, ['--no-create-info', '--complete-insert', $banco, ...self::REFERENCIA]);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $estrutura = preg_replace('/ AUTO_INCREMENT=\d+/', '', $estrutura);

        // Só as migrations que existem como arquivo: o migrate do cliente roda apenas as que vierem depois
        $migrations = $registradas->intersect($arquivos)->values();
        $linhas = $migrations->map(fn ($m) => "('" . str_replace("'", "''", $m) . "',1)")->implode(",\n");

        $sql = "-- Banco base do Cyberly Sistema: estrutura, dados de referência e migrations.\n"
            . "-- Importar num servidor novo. Não importe por cima de um banco em uso.\n"
            . "CREATE DATABASE IF NOT EXISTS `{$banco}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
            . "USE `{$banco}`;\nSET FOREIGN_KEY_CHECKS=0;\n\n"
            . $estrutura . "\n" . $dados
            . "\nINSERT INTO `migrations` (`migration`, `batch`) VALUES\n{$linhas};\n"
            . "SET FOREIGN_KEY_CHECKS=1;\n";

        $saida = database_path('cyberly-base.sql');
        file_put_contents($saida, $sql);

        $this->info(sprintf('Banco base gerado em %s: %d tabelas, %d migrations, %.1f MB.',
            $saida, count($tabelas), $migrations->count(), filesize($saida) / 1048576));
        $this->line('Leve-o no ZIP do sistema e importe no servidor novo.');

        return self::SUCCESS;
    }

    private function mysqldump(array $conexao, array $argumentos): string
    {
        $bin = $this->option('bin');
        $comando = [
            $bin ? rtrim($bin, '\\/') . DIRECTORY_SEPARATOR . 'mysqldump' : 'mysqldump',
            '-h', $conexao['host'], '-P', (string) $conexao['port'], '-u', $conexao['username'],
            '--default-character-set=utf8mb4', '--single-transaction', '--skip-triggers',
            '--skip-comments', '--no-tablespaces',
            ...$argumentos,
        ];

        // A senha vai por variável de ambiente para não aparecer na linha de comando
        $resultado = Process::env(['MYSQL_PWD' => (string) $conexao['password']])->timeout(600)->run($comando);

        if ($resultado->failed()) {
            throw new \RuntimeException('mysqldump falhou (está no PATH? use o Terminal do Laragon ou --bin): '
                . trim($resultado->errorOutput()));
        }

        return $resultado->output();
    }
}
