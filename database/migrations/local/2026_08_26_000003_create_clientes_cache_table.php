<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roda na conexao 'sqlite_local' (cache do caixa), igual as outras
     * migrations dessa pasta local/:
     *   php artisan migrate --database=sqlite_local --path=database/migrations/local
     */
    public function up(): void
    {
        Schema::connection('sqlite_local')->create('clientes_cache', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_pessoa');
            $table->string('nome');
            $table->string('nome_fantasia')->nullable();
            $table->string('cpf_cnpj', 14);
            $table->string('indicador_ie');
            $table->string('ie', 20)->nullable();
            $table->string('cep', 8)->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento', 100)->nullable();
            $table->string('bairro', 100)->nullable();
            $table->string('municipio', 100)->nullable();
            $table->string('cod_municipio', 7)->nullable();
            $table->string('uf', 2)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->dropIfExists('clientes_cache');
    }
};
