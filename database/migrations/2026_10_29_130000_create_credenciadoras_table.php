<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Banco / instituição de pagamento que recebe os pagamentos eletrônicos (registro 1601 do SPED)
        Schema::create('credenciadoras', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100);
            $table->string('cnpj', 14)->unique();
            $table->string('cep', 8)->nullable();
            $table->string('logradouro', 60)->nullable();
            $table->string('numero', 10)->nullable();
            $table->string('complemento', 60)->nullable();
            $table->string('bairro', 60)->nullable();
            $table->string('municipio', 100)->nullable();
            $table->string('cod_municipio', 7)->nullable();
            $table->string('uf', 2)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Qual credenciadora recebe cada meio de pagamento (código do meio da NF-e: 03 crédito, 04 débito, 17 PIX...)
        // Um meio só pode estar em uma credenciadora; uma credenciadora pode receber vários meios.
        Schema::create('credenciadora_meios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credenciadora_id')->constrained('credenciadoras')->cascadeOnDelete();
            $table->char('meio_pagamento', 2)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credenciadora_meios');
        Schema::dropIfExists('credenciadoras');
    }
};
