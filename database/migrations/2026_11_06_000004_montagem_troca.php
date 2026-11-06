<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Montagem NF-e de troca:
 *  - troca_venda_id / troca_nota_fiscal_id: documento de venda devolvido pelo cliente (NFC-e ou NF-e) na NF-e de entrada;
 *  - troca_entrada_id: na NF-e de venda do item novo, a NF-e de entrada (devolução) da mesma troca;
 *  - origem_tipo ganha 'troca'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->foreignId('troca_venda_id')->nullable()->after('entrada_nota_id')->constrained('vendas')->nullOnDelete();
            $table->foreignId('troca_nota_fiscal_id')->nullable()->after('troca_venda_id')->constrained('notas_fiscais')->nullOnDelete();
            $table->foreignId('troca_entrada_id')->nullable()->after('troca_nota_fiscal_id')->constrained('notas_fiscais')->nullOnDelete();
            $table->enum('origem_tipo', ['manual', 'venda', 'pedido', 'devolucao', 'troca'])->default('manual')->change();
        });
    }

    public function down(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->dropConstrainedForeignId('troca_entrada_id');
            $table->dropConstrainedForeignId('troca_nota_fiscal_id');
            $table->dropConstrainedForeignId('troca_venda_id');
            $table->enum('origem_tipo', ['manual', 'venda', 'pedido', 'devolucao'])->default('manual')->change();
        });
    }
};
