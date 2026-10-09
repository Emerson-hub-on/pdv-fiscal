<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fornecedores', function (Blueprint $table) {
            $table->id();
            $table->string('cnpj_cpf', 14)->unique();   // somente dígitos
            $table->string('razao_social', 150);
            $table->string('nome_fantasia', 150)->nullable();
            $table->string('ie', 20)->nullable();
            $table->string('logradouro', 120)->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento', 60)->nullable();
            $table->string('bairro', 60)->nullable();
            $table->string('cep', 8)->nullable();
            $table->string('municipio', 80)->nullable();
            $table->string('cod_municipio', 7)->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('email', 120)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fornecedores');
    }
};
