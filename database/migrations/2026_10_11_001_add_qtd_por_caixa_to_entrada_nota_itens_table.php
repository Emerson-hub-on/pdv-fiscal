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
        Schema::table('entrada_nota_itens', function (Blueprint $table) {
            $table->unsignedInteger('qtd_por_caixa')->default(1)->after('quantidade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entrada_nota_itens', function (Blueprint $table) {
            $table->dropColumn('qtd_por_caixa');
        });
    }
};
