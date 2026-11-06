<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Montagem NF-e de devolução a partir da nota de entrada:
 *  - entrada_nota_itens.n_item_origem: nº do item (nItem) no XML da nota de compra, usado na referência por item da devolução;
 *  - notas_fiscais.entrada_nota_id: nota de entrada devolvida;
 *  - origem_tipo ganha 'devolucao'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entrada_nota_itens', function (Blueprint $table) {
            $table->unsignedSmallInteger('n_item_origem')->nullable()->after('entrada_nota_id');
        });

        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->foreignId('entrada_nota_id')->nullable()->after('pedido_venda_id')
                ->constrained('entradas_nota')->nullOnDelete();
            $table->enum('origem_tipo', ['manual', 'venda', 'pedido', 'devolucao'])->default('manual')->change();
        });
    }

    public function down(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entrada_nota_id');
            $table->enum('origem_tipo', ['manual', 'venda', 'pedido'])->default('manual')->change();
        });

        Schema::table('entrada_nota_itens', function (Blueprint $table) {
            $table->dropColumn('n_item_origem');
        });
    }
};
