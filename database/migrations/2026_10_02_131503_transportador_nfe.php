<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transportadores', function (Blueprint $table) {
            $table->id();
            $table->char('tipo_pessoa', 1);              // F = CPF (motorista autônomo), J = CNPJ
            $table->string('documento', 14)->unique();   // CPF (11 dígitos) ou CNPJ (14, pode ter letras)
            $table->string('nome', 60);                  // razão social ou nome completo (xNome tem limite de 60)
            $table->string('ie', 14)->nullable();        // inscrição estadual ou "ISENTO"
            $table->string('logradouro', 60);
            $table->string('numero', 10);
            $table->string('bairro', 40);
            $table->string('municipio', 60);
            $table->char('uf', 2);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            // Índices adicionais
            $table->index('nome');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transportadores');
    }
};
