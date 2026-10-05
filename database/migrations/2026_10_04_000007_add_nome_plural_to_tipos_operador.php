<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_operador', function (Blueprint $table) {
            $table->string('nome_plural')->nullable()->after('nome');
        });

        DB::table('tipos_operador')->where('slug', 'caixa')->update(['nome_plural' => 'Operadores de Caixa']);
        DB::table('tipos_operador')->where('slug', 'supervisor')->update(['nome_plural' => 'Supervisores']);
        DB::table('tipos_operador')->where('slug', 'fiscal')->update(['nome_plural' => 'Operadores Fiscais']);
    }

    public function down(): void
    {
        Schema::table('tipos_operador', function (Blueprint $table) {
            $table->dropColumn('nome_plural');
        });
    }
};