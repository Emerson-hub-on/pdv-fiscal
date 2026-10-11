<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entrada_nota_itens', function (Blueprint $table) {
            $table->decimal('valor_outras_despesas', 15, 2)->default(0)->after('valor_total');
            $table->decimal('valor_frete', 15, 2)->default(0)->after('valor_outras_despesas');
            $table->decimal('bc_icms', 15, 2)->default(0)->after('valor_frete');
            $table->decimal('valor_icms', 15, 2)->default(0)->after('bc_icms');
            $table->decimal('aliquota_icms', 7, 4)->default(0)->after('valor_icms');
            $table->decimal('valor_ipi', 15, 2)->default(0)->after('aliquota_icms');
            $table->decimal('aliquota_ipi', 7, 4)->default(0)->after('valor_ipi');
        });
    }

    public function down(): void
    {
        Schema::table('entrada_nota_itens', function (Blueprint $table) {
            $table->dropColumn([
                'valor_outras_despesas',
                'valor_frete',
                'bc_icms',
                'valor_icms',
                'aliquota_icms',
                'valor_ipi',
                'aliquota_ipi',
            ]);
        });
    }
};
