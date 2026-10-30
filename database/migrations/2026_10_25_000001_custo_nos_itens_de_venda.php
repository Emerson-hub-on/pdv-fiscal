<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Custo do produto no momento da venda (base do relatório de margem de lucro).
// Fica NULL quando o produto não tinha custo cadastrado: o relatório não conta esses itens na margem.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['venda_itens', 'nota_fiscal_itens'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->decimal('custo_unitario', 15, 4)->nullable();
                $table->decimal('valor_custo', 15, 2)->nullable(); // custo_unitario × quantidade
            });
        }
    }

    public function down(): void
    {
        foreach (['venda_itens', 'nota_fiscal_itens'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn(['custo_unitario', 'valor_custo']);
            });
        }
    }
};
