<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->string('ultimo_arquivo_xml')->nullable()->after('motivo_rejeicao');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn('ultimo_arquivo_xml');
        });
    }
};
