<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendas do restaurante emitidas no caixa (PDV → Puxar Pedido → Pedidos Restaurante):
 * a conta da mesa (comanda) e o pedido do iFood guardam a venda (NFC-e) que nasceu deles,
 * para nenhum caixa emitir o mesmo pedido duas vezes. A venda guarda de onde veio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comandas', function (Blueprint $table) {
            $table->uuid('venda_uuid')->nullable()->after('fechada_onde')->index();
            $table->timestamp('venda_reservada_em')->nullable()->after('venda_uuid');
        });

        Schema::table('comanda_pedidos', function (Blueprint $table) {
            $table->uuid('venda_uuid')->nullable()->after('estoque_devolvido_em')->index();
            $table->timestamp('venda_reservada_em')->nullable()->after('venda_uuid');
        });

        Schema::table('vendas', function (Blueprint $table) {
            $table->unsignedBigInteger('comanda_id')->nullable()->after('pedido_venda_id')->index();
            $table->unsignedBigInteger('comanda_pedido_id')->nullable()->after('comanda_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('vendas', fn (Blueprint $table) => $table->dropColumn(['comanda_id', 'comanda_pedido_id']));
        Schema::table('comanda_pedidos', fn (Blueprint $table) => $table->dropColumn(['venda_uuid', 'venda_reservada_em']));
        Schema::table('comandas', fn (Blueprint $table) => $table->dropColumn(['venda_uuid', 'venda_reservada_em']));
    }
};
