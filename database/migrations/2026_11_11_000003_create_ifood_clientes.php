<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Clientes do iFood: um cadastro por customer.id do iFood (fixo por cliente; o telefone vem mascarado, 0800 +
 * localizador, e não serve para identificar). Os números (pedidos, gasto, último pedido) são calculados dos pedidos;
 * aqui fica o cadastro e o que o iFood informa (segmento e pedidos na loja pelo iFood).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ifood_clientes', function (Blueprint $table) {
            $table->id();
            $table->string('ifood_customer_id', 64)->unique();
            $table->string('nome', 120)->nullable()->index();
            $table->string('cpf', 11)->nullable()->index();          // do pedido ou informado na NFC-e de entrega
            $table->string('segmentacao', 40)->nullable();           // como o iFood classifica o cliente
            $table->unsignedInteger('pedidos_na_loja_ifood')->nullable(); // ordersCountOnMerchant do último pedido
            $table->string('ultimo_endereco', 500)->nullable();
            $table->string('bairro', 80)->nullable();
            $table->string('cidade', 80)->nullable();
            $table->string('uf', 2)->nullable();
            $table->timestamp('primeiro_pedido_em')->nullable();
            $table->timestamp('ultimo_pedido_em')->nullable()->index();
            $table->text('observacao')->nullable();                  // anotação da loja sobre o cliente
            $table->timestamps();
        });

        Schema::table('ifood_pedidos', function (Blueprint $table) {
            $table->unsignedBigInteger('ifood_cliente_id')->nullable()->index()->after('comanda_pedido_id');
        });

        // Cadastra os clientes dos pedidos que já estão no sistema
        Artisan::call('ifood:clientes');
    }

    public function down(): void
    {
        Schema::table('ifood_pedidos', function (Blueprint $table) {
            $table->dropColumn('ifood_cliente_id');
        });
        Schema::dropIfExists('ifood_clientes');
    }
};
