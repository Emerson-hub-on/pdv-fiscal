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
        // Altera a tabela 'entrada_nota_itens' adicionando o campo 'fiscal_manual'
        Schema::table('entrada_nota_itens', function (Blueprint $t) {
            $t->boolean('fiscal_manual')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove a coluna caso a migration seja revertida
        Schema::table('entrada_nota_itens', function (Blueprint $t) {
            $t->dropColumn('fiscal_manual');
        });
    }
};
