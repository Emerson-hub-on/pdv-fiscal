<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlite_local')->table('vendas_pendentes', function (Blueprint $table) {
            // Sem ->constrained() de proposito: clientes_cache aqui e so uma
            // copia local pra referencia/exibicao, o vinculo de verdade (com FK)
            // acontece no banco central quando a venda sobe.
            $table->unsignedBigInteger('cliente_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->table('vendas_pendentes', function (Blueprint $table) {
            $table->dropColumn('cliente_id');
        });
    }
};
