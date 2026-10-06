<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlite_local')->table('pdvs_cache', function (Blueprint $table) {
            $table->boolean('emissao_local')->default(false);
        });

        Schema::connection('sqlite_local')->table('vendas_pendentes', function (Blueprint $table) {
            $table->boolean('fiscal_sync_pendente')->default(false); // mudou a situação fiscal e o servidor ainda não sabe
        });

        // Vendas já emitidas nos testes: o servidor passa a receber os dados fiscais delas
        DB::connection('sqlite_local')->table('vendas_pendentes')
            ->where('status_fiscal', '!=', 'pendente')
            ->update(['fiscal_sync_pendente' => true]);
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->table('vendas_pendentes', fn (Blueprint $table) => $table->dropColumn('fiscal_sync_pendente'));
        Schema::connection('sqlite_local')->table('pdvs_cache', fn (Blueprint $table) => $table->dropColumn('emissao_local'));
    }
};