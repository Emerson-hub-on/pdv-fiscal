<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Impressoras das comandas (balcão, cozinha, churrascaria...): cada uma recebe os itens das categorias
 * do cardápio ligadas a ela, ou o pedido completo. Conexão pela rede (IP:porta) ou USB no servidor
 * (impressora compartilhada no Windows ou /dev/usb/lp0 no Linux).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('comanda_impressoras')) {
            Schema::create('comanda_impressoras', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 60);
                $table->string('conexao', 10)->default('rede');          // rede | usb
                $table->string('endereco', 120);                           // IP (rede) ou nome compartilhado / caminho (usb)
                $table->unsignedInteger('porta')->default(9100);
                $table->unsignedTinyInteger('colunas')->default(48);       // 48 = 80 mm, 32 = 58 mm
                $table->unsignedTinyInteger('copias')->default(1);
                $table->boolean('pedido_completo')->default(false);        // imprime todos os itens (ex.: balcão)
                $table->boolean('sem_categoria')->default(false);          // também os itens sem categoria
                $table->boolean('mostrar_precos')->default(false);
                $table->boolean('ativo')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('comanda_impressora_categoria')) {
            Schema::create('comanda_impressora_categoria', function (Blueprint $table) {
                $table->foreignId('impressora_id')->constrained('comanda_impressoras')->cascadeOnDelete();
                $table->foreignId('cardapio_categoria_id')->constrained('cardapio_categorias')->cascadeOnDelete();
                $table->primary(['impressora_id', 'cardapio_categoria_id'], 'cmd_imp_cat_pk');
            });
        }

        Schema::table('comanda_pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('comanda_pedidos', 'impresso_em')) {
                $table->timestamp('impresso_em')->nullable();
            }
            if (!Schema::hasColumn('comanda_pedidos', 'impressao_erro')) {
                $table->string('impressao_erro', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('comanda_pedidos', function (Blueprint $table) {
            $table->dropColumn(['impresso_em', 'impressao_erro']);
        });
        Schema::dropIfExists('comanda_impressora_categoria');
        Schema::dropIfExists('comanda_impressoras');
    }
};
