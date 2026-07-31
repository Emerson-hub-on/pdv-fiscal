<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::create('vendas_pendentes', function (Blueprint $table) {
        $table->id();
        $table->uuid('uuid')->unique(); // identificador global, evita duplicar ao sincronizar
        $table->unsignedBigInteger('caixa_id_central')->nullable(); // preenchido apos caixa existir no central
        $table->unsignedBigInteger('operador_id_central');
        $table->decimal('total', 12, 2);
        $table->string('forma_pagamento');
        $table->json('itens'); // snapshot dos itens vendidos (produto_id, variante_id, quantidade, preco_unitario)
        $table->enum('status', ['pendente_sync', 'sincronizada', 'erro_sync'])->default('pendente_sync');
        $table->text('erro_sync_mensagem')->nullable();
        $table->timestamp('vendida_em');
        $table->timestamp('sincronizada_em')->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('vendas_pendentes');
}
};
