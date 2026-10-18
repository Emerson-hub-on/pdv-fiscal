<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordens_servico', function (Blueprint $t) {
            // vazio = usa o texto padrão (OrdemServico::TERMO_PADRAO) na impressão
            $t->text('termo_responsabilidade')->nullable()->after('observacoes');
        });
    }

    public function down(): void
    {
        Schema::table('ordens_servico', function (Blueprint $t) {
            $t->dropColumn('termo_responsabilidade');
        });
    }
};
