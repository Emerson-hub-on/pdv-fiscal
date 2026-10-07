<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdvs', function (Blueprint $table) {
            $table->boolean('emissao_local')->default(true)->change(); // padrão: o caixa emite
            $table->dateTime('emissor_alterado_em')->nullable();
            $table->unsignedBigInteger('emissor_alterado_por')->nullable();
            $table->string('emissor_motivo')->nullable();
        });

        DB::table('pdvs')->update(['emissao_local' => true]);
    }

    public function down(): void
    {
        Schema::table('pdvs', function (Blueprint $table) {
            $table->dropColumn(['emissor_alterado_em', 'emissor_alterado_por', 'emissor_motivo']);
            $table->boolean('emissao_local')->default(false)->change();
        });
    }
};