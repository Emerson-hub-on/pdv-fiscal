<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('produto_variantes', 'sku')) {
            Schema::table('produto_variantes', function (Blueprint $table) {
                $table->dropColumn('sku');
            });
        }
    }

    public function down(): void
    {
        // Recria a coluna vazia: os valores de SKU apagados não voltam.
        if (! Schema::hasColumn('produto_variantes', 'sku')) {
            Schema::table('produto_variantes', function (Blueprint $table) {
                $table->string('sku')->nullable()->after('tamanho');
            });
        }
    }
};
