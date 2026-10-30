<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Servidor (MySQL): cópia das ocorrências registradas nos caixas (descontos, cancelamentos de NFC-e
 * e autorizações do supervisor). Sobe pelo SyncService e alimenta os relatórios de caixa do painel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caixa_ocorrencias', function (Blueprint $table) {
            $table->id();
            $table->string('caixa_uuid')->nullable()->index();
            $table->string('venda_uuid')->nullable()->index();
            $table->string('tipo', 30);
            $table->string('acao', 50)->nullable();
            $table->string('descricao')->nullable();
            $table->decimal('valor', 12, 2)->default(0);
            $table->unsignedBigInteger('operador_id')->nullable();
            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->string('supervisor_nome')->nullable();
            $table->boolean('liberado_operador')->default(false);
            $table->timestamp('ocorrida_em')->nullable();

            // Origem: computador do caixa + id no SQLite dele (o envio é idempotente por esse par)
            $table->string('maquina', 100);
            $table->unsignedBigInteger('local_id');
            $table->unique(['maquina', 'local_id']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caixa_ocorrencias');
    }
};
