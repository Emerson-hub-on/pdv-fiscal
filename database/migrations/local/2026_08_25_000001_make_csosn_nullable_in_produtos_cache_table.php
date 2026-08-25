<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMPORTANTE: essa migration roda na conexao 'sqlite_local' (o cache do
     * caixa/PDV), nao na conexao padrao do MySQL central. Se o seu projeto
     * nao tiver isso configurado por padrao, rode com:
     *   php artisan migrate --database=sqlite_local --path=database/migrations/local
     * (ajuste o --path pra onde essa migration for salva, se voce mantiver
     * as migrations do cache local separadas das do banco central)
     */
    public function up(): void
    {
        Schema::connection('sqlite_local')->table('produtos_cache', function (Blueprint $table) {
            $table->string('csosn')->nullable()->change();
            $table->string('cfop_padrao')->nullable()->change();
        });
    }

    public function down(): void
    {
        // SQLite nao suporta bem re-adicionar NOT NULL via change() de forma
        // simples sem recriar a tabela - deixando o down() vazio de proposito,
        // ja que voltar pra NOT NULL quebraria produtos de Lucro Real/Presumido
        // que legitimamente nao tem csosn.
    }
};
