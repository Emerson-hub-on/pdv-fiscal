<?php

use App\Models\Cliente;
use App\Models\FormaPagamento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_venda', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('numero')->unique();
            $table->date('data_pedido');
            $table->date('previsao_entrega')->nullable();
            $table->foreignId('operador_id')->constrained('users');
            // usa o nome real da tabela dos models, seja qual for
            $table->foreignId('cliente_id')->nullable()->constrained((new Cliente)->getTable());
            $table->foreignId('forma_pagamento_id')->nullable()->constrained((new FormaPagamento)->getTable())->nullOnDelete();
            $table->decimal('valor_total', 14, 2)->default(0);
            $table->string('status', 15)->default('finalizado')->index(); // finalizado | faturado | cancelado
            $table->text('descricao')->nullable();
            $table->timestamps();
        });

        Schema::create('pedido_venda_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_venda_id')->constrained('pedidos_venda')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos');
            $table->foreignId('produto_variante_id')->nullable()->constrained('produto_variantes')->nullOnDelete();
            $table->string('codigo_barras', 20)->nullable();
            $table->string('descricao', 255);
            $table->string('unidade', 6)->default('UN');
            $table->decimal('quantidade', 12, 3);
            $table->decimal('valor_unitario', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_venda_itens');
        Schema::dropIfExists('pedidos_venda');
    }
};
