<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caixa fechado por um supervisor ou administrador no lugar do operador que abriu.
 * Vazio = o próprio operador fechou.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caixas', function (Blueprint $table) {
            $table->unsignedBigInteger('fechado_por_id')->nullable()->after('observacao')->index(); // users.id
            $table->string('fechado_por_nome')->nullable()->after('fechado_por_id');
            $table->string('fechado_por_perfil', 20)->nullable()->after('fechado_por_nome'); // administrador | supervisor
        });
    }

    public function down(): void
    {
        Schema::table('caixas', function (Blueprint $table) {
            $table->dropIndex(['fechado_por_id']);
            $table->dropColumn(['fechado_por_id', 'fechado_por_nome', 'fechado_por_perfil']);
        });
    }
};
