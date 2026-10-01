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
            $table->decimal('valor_outras_despesas', 15, 2)->default(0)->after('valor_desconto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nota_fiscal_itens', function (Blueprint $table) {
            $table->dropColumn('valor_outras_despesas');
        });
    }
};
