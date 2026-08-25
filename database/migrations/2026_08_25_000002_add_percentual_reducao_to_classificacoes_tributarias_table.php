<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classificacoes_tributarias', function (Blueprint $table) {
            // Percentual de reducao da aliquota base (transicao), quando o cClassTrib
            // legalmente tem direito a reducao (ex: cesta basica). Nullable/0 = sem reducao.
            $table->decimal('percentual_reducao_ibs', 6, 4)->nullable()->after('cst_descricao');
            $table->decimal('percentual_reducao_cbs', 6, 4)->nullable()->after('percentual_reducao_ibs');
        });
    }

    public function down(): void
    {
        Schema::table('classificacoes_tributarias', function (Blueprint $table) {
            $table->dropColumn(['percentual_reducao_ibs', 'percentual_reducao_cbs']);
        });
    }
};
