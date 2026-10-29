<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PIS/COFINS das entradas, do ponto de vista da EMPRESA (crédito), não do fornecedor:
     * CST de entrada (50-56, 70-75, 98, 99), natureza da base de crédito (NAT_BC_CRED), base,
     * alíquotas e valores. O regime (cumulativo / não cumulativo) fica na Configuração do SPED.
     */
    public function up(): void
    {
        Schema::table('entrada_nota_itens', function (Blueprint $table) {
            $table->string('cst_pis_cofins', 2)->nullable();
            $table->string('nat_bc_cred', 2)->nullable();
            $table->decimal('bc_pis_cofins', 15, 2)->default(0);
            $table->decimal('aliquota_pis', 8, 4)->default(0);
            $table->decimal('aliquota_cofins', 8, 4)->default(0);

            if (! Schema::hasColumn('entrada_nota_itens', 'valor_pis')) {
                $table->decimal('valor_pis', 15, 2)->default(0);
            }
            if (! Schema::hasColumn('entrada_nota_itens', 'valor_cofins')) {
                $table->decimal('valor_cofins', 15, 2)->default(0);
            }

            // true = CST/base/alíquotas digitados pelo operador; o cálculo automático não sobrescreve
            $table->boolean('pis_cofins_manual')->default(false);
        });

        Schema::table('sped_configuracoes', function (Blueprint $table) {
            // cumulativo (Lucro Presumido) ou nao_cumulativo (Lucro Real)
            $table->string('regime_pis_cofins', 20)->nullable()->after('cod_receita_icms');
        });
    }

    public function down(): void
    {
        Schema::table('entrada_nota_itens', function (Blueprint $table) {
            $table->dropColumn(['cst_pis_cofins', 'nat_bc_cred', 'bc_pis_cofins', 'aliquota_pis', 'aliquota_cofins', 'pis_cofins_manual']);
        });

        Schema::table('sped_configuracoes', function (Blueprint $table) {
            $table->dropColumn('regime_pis_cofins');
        });
    }
};
