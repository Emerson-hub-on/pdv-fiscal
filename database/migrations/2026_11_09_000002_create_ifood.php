<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integração com o iFood (aplicativo distribuído):
 *  - ifood_configuracoes: credenciais do app, loja (merchant), tokens e preferências (aceite automático);
 *  - ifood_produtos: cardápio importado do iFood e a ligação com o produto do sistema (para emitir depois);
 *  - ifood_pedidos: dados do pedido no iFood (tipo, cliente, entrega, pagamentos) ligados ao pedido da Lista de Pedidos.
 * Os pedidos do iFood não têm mesa nem (às vezes) produto ligado: mesa_id e produto_id passam a aceitar vazio.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ifood_configuracoes')) {
            Schema::create('ifood_configuracoes', function (Blueprint $table) {
                $table->id();
                $table->string('modo_autenticacao', 20)->default('distribuido'); // distribuido | centralizado
                $table->string('client_id', 120)->nullable();
                $table->text('client_secret')->nullable();          // criptografado
                $table->string('merchant_id', 60)->nullable();
                $table->boolean('ativo')->default(false);
                $table->boolean('aceite_automatico')->default(false);
                $table->text('access_token')->nullable();           // criptografado
                $table->text('refresh_token')->nullable();          // criptografado
                $table->timestamp('token_expira_em')->nullable();
                $table->string('user_code', 30)->nullable();        // vínculo da loja (app distribuído)
                $table->text('code_verifier')->nullable();          // criptografado
                $table->string('verification_url', 255)->nullable();
                $table->timestamp('user_code_expira_em')->nullable();
                $table->timestamp('ultimo_polling_em')->nullable();
                $table->string('ultimo_erro', 500)->nullable();
                $table->timestamp('ultimo_erro_em')->nullable();
                $table->timestamp('cardapio_importado_em')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ifood_produtos')) {
            Schema::create('ifood_produtos', function (Blueprint $table) {
                $table->id();
                $table->string('ifood_item_id', 60)->unique();
                $table->string('ifood_product_id', 60)->nullable()->index();
                $table->string('catalogo_id', 60)->nullable();
                $table->string('categoria', 120)->nullable();
                $table->string('nome', 255);
                $table->text('descricao')->nullable();
                $table->string('codigo_externo', 60)->nullable()->index();
                $table->string('ean', 20)->nullable();
                $table->decimal('preco_ifood', 12, 2)->default(0);   // como está no iFood
                $table->decimal('preco', 12, 2)->default(0);         // valor no sistema (começa igual; o operador pode mudar)
                $table->string('imagem_url', 500)->nullable();
                $table->string('status', 20)->default('AVAILABLE');
                // Produto do sistema que este item do iFood representa (para emitir a venda depois)
                $table->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
                $table->unsignedBigInteger('produto_variante_id')->nullable()->index();
                $table->timestamp('importado_em')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ifood_pedidos')) {
            Schema::create('ifood_pedidos', function (Blueprint $table) {
                $table->id();
                $table->string('ifood_order_id', 60)->unique();
                $table->string('display_id', 20)->nullable();
                $table->foreignId('comanda_pedido_id')->nullable()->constrained('comanda_pedidos')->nullOnDelete();
                $table->string('merchant_id', 60)->nullable();
                $table->string('tipo', 20)->nullable();              // DELIVERY | TAKEOUT | DINE_IN | INDOOR
                $table->string('momento', 20)->nullable();           // IMMEDIATE | SCHEDULED
                $table->string('status', 30)->default('PLACED');     // último evento do iFood (PLACED, CONFIRMED, CANCELLED...)
                $table->string('entregue_por', 20)->nullable();      // IFOOD | MERCHANT
                $table->string('cliente_nome', 120)->nullable();
                $table->string('cliente_telefone', 40)->nullable();
                $table->string('localizador', 20)->nullable();
                $table->string('endereco', 500)->nullable();
                $table->string('codigo_retirada', 20)->nullable();
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('taxa_entrega', 12, 2)->default(0);
                $table->decimal('descontos', 12, 2)->default(0);
                $table->decimal('taxas_adicionais', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->decimal('pago_online', 12, 2)->default(0);
                $table->decimal('cobrar_na_entrega', 12, 2)->default(0);
                $table->json('pagamentos')->nullable();
                $table->string('observacoes', 500)->nullable();
                $table->timestamp('agendado_para')->nullable();
                $table->timestamp('criado_ifood_em')->nullable();
                $table->timestamp('confirmado_em')->nullable();
                $table->string('ultimo_erro', 500)->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('comanda_pedidos', function (Blueprint $table) {
            $table->unsignedBigInteger('mesa_id')->nullable()->change();
        });
        Schema::table('comanda_pedido_itens', function (Blueprint $table) {
            $table->unsignedBigInteger('produto_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ifood_pedidos');
        Schema::dropIfExists('ifood_produtos');
        Schema::dropIfExists('ifood_configuracoes');
    }
};
