<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tudo nullable: veículos já cadastrados continuam válidos.
        // A obrigatoriedade dos campos é checada na hora de montar o MDF-e.
        Schema::table('veiculos', function (Blueprint $table) {
            $table->string('tipo', 10)->default('tracao')->after('transportador_id'); // tracao | reboque
            $table->unsignedInteger('tara_kg')->nullable()->after('rntrc');
            $table->unsignedInteger('capacidade_kg')->nullable()->after('tara_kg');
            $table->unsignedInteger('capacidade_m3')->nullable()->after('capacidade_kg');
            $table->string('tipo_rodado', 2)->nullable()->after('capacidade_m3');
            $table->string('tipo_carroceria', 2)->nullable()->after('tipo_rodado');
        });
    }

    public function down(): void
    {
        Schema::table('veiculos', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'tara_kg', 'capacidade_kg', 'capacidade_m3', 'tipo_rodado', 'tipo_carroceria']);
        });
    }
};
