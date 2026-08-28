<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            // CPF avulso informado na nota, sem cadastro completo de cliente.
            // Mutuamente exclusivo com cliente_id na pratica (um ou outro,
            // nunca os dois - a UI so deixa escolher um).
            $table->string('cpf_na_nota', 11)->nullable()->after('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn('cpf_na_nota');
        });
    }
};
