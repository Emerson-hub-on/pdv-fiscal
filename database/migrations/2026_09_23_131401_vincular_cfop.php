<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->foreignId('cfop_saida_id')->nullable()->after('finalidade')->constrained('cfop_saida');
        });

        Schema::table('nota_fiscal_itens', function (Blueprint $table) {
            $table->dropColumn('cfop');
        });
    }

    public function down(): void
    {
        Schema::table('nota_fiscal_itens', function (Blueprint $table) {
            $table->string('cfop', 4)->nullable();
        });

        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cfop_saida_id');
        });
    }
};