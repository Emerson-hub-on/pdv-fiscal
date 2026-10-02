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
        // Altera a tabela nota_fiscal_itens
        Schema::table('nota_fiscal_itens', function (Blueprint $table) {
            $table->decimal('valor_frete', 15, 2)->default(0)->after('valor_outras_despesas');
        });

        // Altera a tabela notas_fiscais
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->boolean('frete_por_item')->default(false)->after('valor_frete');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove o campo da tabela notas_fiscais
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->dropColumn('frete_por_item');
        });

        // Remove o campo da tabela nota_fiscal_itens
        Schema::table('nota_fiscal_itens', function (Blueprint $table) {
            $table->dropColumn('valor_frete');
        });
    }
};
