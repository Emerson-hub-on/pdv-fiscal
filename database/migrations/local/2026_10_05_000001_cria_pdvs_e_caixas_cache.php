<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlite_local')->create('pdvs_cache', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // mesmo id do central
            $table->string('nome');
            $table->string('serie_nfce')->nullable();
            $table->unsignedBigInteger('numero_atual_nfce')->default(0); // só exibição
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Espelho dos caixas ABERTOS no servidor (o dono do dado continua sendo o central)
        Schema::connection('sqlite_local')->create('caixas_cache', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // id do caixa no central
            $table->unsignedBigInteger('operador_id');   // users.id no central
            $table->unsignedBigInteger('pdv_id');
            $table->dateTime('data_abertura');
            $table->decimal('valor_abertura', 10, 2)->default(0);
            $table->string('status')->default('aberto');
            $table->timestamps();

            $table->index(['operador_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->dropIfExists('caixas_cache');
        Schema::connection('sqlite_local')->dropIfExists('pdvs_cache');
    }
};