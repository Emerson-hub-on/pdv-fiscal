<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfop_saida', function (Blueprint $table) {
            $table->string('natureza_operacao_padrao')->nullable()->after('descricao');
            $table->unsignedTinyInteger('finalidade_padrao')->default(1)->after('natureza_operacao_padrao');
        });
    }

    public function down(): void
    {
        Schema::table('cfop_saida', function (Blueprint $table) {
            $table->dropColumn(['natureza_operacao_padrao', 'finalidade_padrao']);
        });
    }
};