<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::create('vendas', function (Blueprint $table) {
        $table->id();
        $table->foreignId('caixa_id')->constrained('caixas');
        $table->unsignedBigInteger('cliente_id')->nullable(); // vira FK quando criarmos clientes
        $table->foreignId('operador_id')->constrained('users');
        $table->decimal('total', 12, 2);
        $table->enum('forma_pagamento', ['dinheiro', 'pix', 'credito', 'debito'])->nullable();
        $table->enum('status', ['pendente', 'emitida', 'cancelada'])->default('pendente');

        $table->string('chave_nfe', 44)->nullable();
        $table->string('protocolo_nfe')->nullable();
        $table->text('motivo_cancelamento')->nullable();

        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('vendas');
}
};
