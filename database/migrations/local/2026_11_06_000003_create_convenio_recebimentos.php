<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite do caixa: baixas de convênio recebidas no caixa (fila para o servidor, como as vendas).
 * Também alimentam o relatório de fechamento (o dinheiro recebido entra na gaveta).
 */
return new class extends Migration
{
    protected $connection = 'sqlite_local';

    public function up(): void
    {
        Schema::connection('sqlite_local')->create('convenio_recebimentos', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('venda_uuid', 36)->index();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->string('cliente_nome')->nullable();
            $table->unsignedInteger('numero_nfce')->nullable();
            $table->decimal('valor', 12, 2);
            $table->string('forma_pagamento', 20);
            $table->string('caixa_uuid', 36)->nullable()->index();
            $table->unsignedBigInteger('operador_id_central')->nullable();
            $table->timestamp('recebido_em')->nullable();
            $table->string('status', 15)->default('pendente_sync'); // pendente_sync | sincronizada | erro_sync
            $table->string('erro_sync_mensagem', 500)->nullable();
            $table->timestamp('sincronizado_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->dropIfExists('convenio_recebimentos');
    }
};
