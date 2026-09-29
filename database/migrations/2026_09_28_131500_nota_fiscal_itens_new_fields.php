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
            // Seus novos campos manuais
            $table->decimal('bc_icms_manual', 12, 2)->nullable()->after('ref_nitem');
            $table->decimal('valor_icms_manual', 12, 2)->nullable()->after('bc_icms_manual');
            $table->decimal('aliquota_icms_manual', 8, 4)->nullable()->after('valor_icms_manual');
            $table->decimal('valor_ipi_manual', 12, 2)->nullable()->after('aliquota_icms_manual');
            $table->decimal('aliquota_ipi_manual', 8, 4)->nullable()->after('valor_ipi_manual');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nota_fiscal_itens', function (Blueprint $table) {
            // Remove as colunas em ordem inversa caso faça rollback
            $table->dropColumn([
                'bc_icms_manual',
                'valor_icms_manual',
                'aliquota_icms_manual',
                'valor_ipi_manual',
                'aliquota_ipi_manual'
            ]);
        });
    }
};
