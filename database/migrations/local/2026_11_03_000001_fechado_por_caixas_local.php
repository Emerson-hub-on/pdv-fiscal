<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Caixa fechado por um supervisor ou administrador no lugar do operador (vazio = o próprio operador fechou). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlite_local')->table('caixas_local', function (Blueprint $table) {
            $table->unsignedBigInteger('fechado_por_id')->nullable();
            $table->string('fechado_por_nome')->nullable();
            $table->string('fechado_por_perfil', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->table('caixas_local', function (Blueprint $table) {
            $table->dropColumn(['fechado_por_id', 'fechado_por_nome', 'fechado_por_perfil']);
        });
    }
};
