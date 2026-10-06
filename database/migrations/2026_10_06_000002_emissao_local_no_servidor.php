<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdvs', function (Blueprint $table) {
            $table->boolean('emissao_local')->default(false)->after('ativo'); // true = o caixa é o dono da numeração
        });

        Schema::table('vendas', function (Blueprint $table) {
            $table->longText('xml_nfce')->nullable(); // XML autorizado, arquivado pelo servidor
        });
    }

    public function down(): void
    {
        Schema::table('vendas', fn (Blueprint $table) => $table->dropColumn('xml_nfce'));
        Schema::table('pdvs', fn (Blueprint $table) => $table->dropColumn('emissao_local'));
    }
};