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
        Schema::table('nota_fiscal_itens', function (Blueprint $table) {
            $table->foreignId('produto_variante_id')
                  ->nullable()
                  ->after('produto_id')
                  ->constrained('produto_variantes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nota_fiscal_itens', function (Blueprint $table) {
            $table->dropForeign(['produto_variante_id']);
            $table->dropColumn('produto_variante_id');
        });
    }
};
