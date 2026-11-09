<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedidos do iFood: acompanhamento dos eventos que pedem ação da loja.
 *  - codigo_entrega_solicitado_em: o iFood pediu o código de entrega (entrega própria) para concluir o pedido;
 *  - cancelamento_solicitado_em: a loja pediu o cancelamento e espera a resposta do iFood;
 *  - cancelado_por: loja | ifood (quem cancelou: o iFood/cliente → avisa a cozinha);
 *  - disputa: pedido de cancelamento / reclamação do cliente aguardando a loja aceitar ou recusar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ifood_pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('ifood_pedidos', 'codigo_entrega_solicitado_em')) {
                $table->timestamp('codigo_entrega_solicitado_em')->nullable()->after('confirmado_em');
            }
            if (!Schema::hasColumn('ifood_pedidos', 'cancelamento_solicitado_em')) {
                $table->timestamp('cancelamento_solicitado_em')->nullable()->after('codigo_entrega_solicitado_em');
            }
            if (!Schema::hasColumn('ifood_pedidos', 'cancelado_por')) {
                $table->string('cancelado_por', 10)->nullable()->after('cancelamento_solicitado_em');
            }
            if (!Schema::hasColumn('ifood_pedidos', 'disputa')) {
                $table->json('disputa')->nullable()->after('cancelado_por');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ifood_pedidos', function (Blueprint $table) {
            $table->dropColumn(['codigo_entrega_solicitado_em', 'cancelamento_solicitado_em', 'cancelado_por', 'disputa']);
        });
    }
};
