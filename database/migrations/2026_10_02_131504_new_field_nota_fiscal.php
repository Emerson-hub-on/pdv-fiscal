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
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->foreignId('transportador_id')->nullable()->after('mod_frete')
                  ->constrained('transportadores')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            // Remove a restrição da chave estrangeira
            $table->dropForeign(['transportador_id']);
            // Remove a coluna
            $table->dropColumn('transportador_id');
        });
    }
};
