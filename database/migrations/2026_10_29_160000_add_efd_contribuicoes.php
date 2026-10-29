<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contas contábeis do COD_CTA / registro 0500 da EFD-Contribuições (do plano de contas do contador)
        Schema::table('sped_configuracoes', function (Blueprint $table) {
            $table->string('cod_cta_receita', 60)->nullable()->after('regime_pis_cofins');
            $table->string('nome_cta_receita', 60)->nullable()->after('cod_cta_receita');
            $table->string('cod_cta_compras', 60)->nullable()->after('nome_cta_receita');
            $table->string('nome_cta_compras', 60)->nullable()->after('cod_cta_compras');
        });

        // O histórico passa a guardar os dois arquivos: SPED Fiscal e EFD-Contribuições
        Schema::table('sped_geracoes', function (Blueprint $table) {
            $table->string('tipo', 20)->default('icms_ipi')->after('id');
            $table->json('totais')->nullable()->after('saldo_credor_transportar');
        });
    }

    public function down(): void
    {
        Schema::table('sped_geracoes', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'totais']);
        });

        Schema::table('sped_configuracoes', function (Blueprint $table) {
            $table->dropColumn(['cod_cta_receita', 'nome_cta_receita', 'cod_cta_compras', 'nome_cta_compras']);
        });
    }
};
