<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Os impostos digitados pelo operador (destaque manual) passam a ficar nos mesmos campos dos impostos
// calculados: bc_icms, aliquota_icms, valor_icms, aliquota_ipi e valor_ipi. A coluna impostos_manuais
// só indica que esses valores foram digitados (e não devem ser recalculados pela emissão).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nota_fiscal_itens', function (Blueprint $table) {
            $table->decimal('aliquota_ipi', 8, 4)->default(0)->after('valor_ipi');
            $table->boolean('impostos_manuais')->default(false)->after('aliquota_ipi');
        });

        // Copia o que já estava digitado nos campos manuais (rascunhos e notas já emitidas)
        DB::statement('
            UPDATE nota_fiscal_itens
            SET impostos_manuais = 1,
                bc_icms = COALESCE(bc_icms_manual, 0),
                valor_icms = COALESCE(valor_icms_manual, 0),
                aliquota_icms = COALESCE(aliquota_icms_manual, 0),
                valor_ipi = COALESCE(valor_ipi_manual, 0),
                aliquota_ipi = COALESCE(aliquota_ipi_manual, 0)
            WHERE bc_icms_manual IS NOT NULL
               OR valor_icms_manual IS NOT NULL
               OR aliquota_icms_manual IS NOT NULL
               OR valor_ipi_manual IS NOT NULL
               OR aliquota_ipi_manual IS NOT NULL
        ');

        Schema::table('nota_fiscal_itens', function (Blueprint $table) {
            $table->dropColumn([
                'bc_icms_manual', 'valor_icms_manual', 'aliquota_icms_manual',
                'valor_ipi_manual', 'aliquota_ipi_manual',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('nota_fiscal_itens', function (Blueprint $table) {
            $table->decimal('bc_icms_manual', 12, 2)->nullable();
            $table->decimal('valor_icms_manual', 12, 2)->nullable();
            $table->decimal('aliquota_icms_manual', 8, 4)->nullable();
            $table->decimal('valor_ipi_manual', 12, 2)->nullable();
            $table->decimal('aliquota_ipi_manual', 8, 4)->nullable();
        });

        DB::statement('
            UPDATE nota_fiscal_itens
            SET bc_icms_manual = bc_icms,
                valor_icms_manual = valor_icms,
                aliquota_icms_manual = aliquota_icms,
                valor_ipi_manual = valor_ipi,
                aliquota_ipi_manual = aliquota_ipi
            WHERE impostos_manuais = 1
        ');

        Schema::table('nota_fiscal_itens', function (Blueprint $table) {
            $table->dropColumn(['aliquota_ipi', 'impostos_manuais']);
        });
    }
};
