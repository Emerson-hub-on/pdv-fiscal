<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Guarda em venda_itens os impostos que foram para o XML da NFC-e (base dos relatórios de vendas)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venda_itens', function (Blueprint $table) {
            $table->string('descricao')->nullable()->after('produto_variante_id');
            $table->string('codigo_produto', 60)->nullable()->after('descricao');
            $table->string('ncm', 8)->nullable()->after('codigo_produto');
            $table->string('cfop', 4)->nullable()->after('ncm');
            $table->string('cst_csosn', 3)->nullable()->after('cfop');
            $table->decimal('valor_bruto', 15, 2)->nullable();
            $table->decimal('desconto_efetivo', 15, 2)->default(0); // desconto do item + rateio do desconto global
            $table->decimal('bc_icms', 15, 2)->default(0);
            $table->decimal('aliquota_icms', 7, 4)->default(0);
            $table->decimal('valor_icms', 15, 2)->default(0);
            $table->decimal('valor_pis', 15, 2)->default(0);
            $table->decimal('valor_cofins', 15, 2)->default(0);
            $table->decimal('valor_ibs', 15, 2)->default(0);
            $table->decimal('valor_cbs', 15, 2)->default(0);
            $table->timestamp('impostos_registrados_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('venda_itens', function (Blueprint $table) {
            $table->dropColumn([
                'descricao', 'codigo_produto', 'ncm', 'cfop', 'cst_csosn', 'valor_bruto', 'desconto_efetivo',
                'bc_icms', 'aliquota_icms', 'valor_icms', 'valor_pis', 'valor_cofins', 'valor_ibs', 'valor_cbs',
                'impostos_registrados_em',
            ]);
        });
    }
};
