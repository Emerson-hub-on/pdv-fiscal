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
        Schema::table('cfop_saida', function (Blueprint $table) {
            // Seu código original aqui:
            $table->boolean('destacar_bases')->default(false)->after('movimenta_estoque');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cfop_saida', function (Blueprint $table) {
            // Remove a coluna caso você precise reverter a migration
            $table->dropColumn('destacar_bases');
        });
    }
};
