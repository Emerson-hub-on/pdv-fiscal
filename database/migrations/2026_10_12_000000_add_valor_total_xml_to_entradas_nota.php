<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entradas_nota', function (Blueprint $table) {
            // total do XML (produtos − desconto + frete + outras despesas); null em entradas manuais
            $table->decimal('valor_total_xml', 15, 2)->nullable()->after('valor_total');
        });
    }

    public function down(): void
    {
        Schema::table('entradas_nota', function (Blueprint $table) {
            $table->dropColumn('valor_total_xml');
        });
    }
};
