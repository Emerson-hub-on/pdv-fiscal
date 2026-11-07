<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedidos das comandas: o garçom monta no celular (itens do cardápio, quantidade e observação) e
 * "Finalizar pedido" grava aqui. Uma mesa pode ter vários pedidos enquanto está ocupada.
 * - uuid: gerado no celular; reenviar o mesmo pedido (toque duplo, conexão caiu) não duplica
 * - enviado_em: é daqui que sai o tempo de espera de cada pedido
 * - entregue_em: a mesa foi para "Pedido entregue" (o pedido saiu da espera)
 * - fechado_em: a mesa voltou para "Disponível" (os pedidos dela saem da mesa)
 * Os itens guardam nome e preço do momento do pedido (o cadastro pode mudar depois).
 * Estoque NÃO é baixado aqui: isso fica para quando a comanda for cobrada no caixa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comanda_pedidos', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('mesa_id')->constrained('mesas')->cascadeOnDelete();
            $t->foreignId('garcom_id')->nullable()->constrained('users')->nullOnDelete();
            $t->decimal('total', 12, 2)->default(0);
            $t->timestamp('enviado_em');
            $t->timestamp('entregue_em')->nullable();
            $t->timestamp('fechado_em')->nullable();
            $t->timestamps();

            $t->index(['mesa_id', 'fechado_em']);
        });

        Schema::create('comanda_pedido_itens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pedido_id')->constrained('comanda_pedidos')->cascadeOnDelete();
            $t->foreignId('cardapio_item_id')->nullable()->constrained('cardapio_itens')->nullOnDelete();
            $t->foreignId('produto_id')->constrained('produtos');
            $t->foreignId('produto_variante_id')->nullable()->constrained('produto_variantes')->nullOnDelete();
            $t->string('nome', 255);
            $t->unsignedSmallInteger('quantidade');
            $t->decimal('preco_unitario', 12, 2);
            $t->decimal('subtotal', 12, 2);
            $t->string('observacao', 200)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comanda_pedido_itens');
        Schema::dropIfExists('comanda_pedidos');
    }
};
