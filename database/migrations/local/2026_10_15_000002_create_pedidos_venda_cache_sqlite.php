<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite LOCAL do caixa (conexão sqlite_local): espelho dos pedidos de venda finalizados
 * e vínculo da venda do caixa com o pedido de origem.
 * Coloque na mesma pasta das demais migrations do SQLite local.
 */
return new class extends Migration
{
    protected $connection = 'sqlite_local';

    public function up(): void
    {
        Schema::connection('sqlite_local')->create('pedidos_venda_cache', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // mesmo id do servidor
            $table->unsignedInteger('numero');
            $table->date('data_pedido');
            $table->date('previsao_entrega')->nullable();
            $table->unsignedBigInteger('operador_id')->nullable();
            $table->string('operador_nome')->nullable();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->string('cliente_nome')->nullable();
            $table->decimal('valor_total', 14, 2)->default(0);
            $table->text('descricao')->nullable();
            $table->timestamps();
        });

        Schema::connection('sqlite_local')->create('pedido_venda_itens_cache', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('pedido_venda_id')->index();
            $table->unsignedBigInteger('produto_id');
            $table->unsignedBigInteger('produto_variante_id')->nullable();
            $table->string('codigo_barras')->nullable();
            $table->string('descricao');
            $table->string('unidade', 6)->default('UN');
            $table->decimal('quantidade', 12, 3);
            $table->decimal('valor_unitario', 12, 2);
            $table->timestamps();
        });

        Schema::connection('sqlite_local')->table('vendas_pendentes', function (Blueprint $table) {
            $table->unsignedBigInteger('pedido_venda_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->table('vendas_pendentes', function (Blueprint $table) {
            $table->dropColumn('pedido_venda_id');
        });
        Schema::connection('sqlite_local')->dropIfExists('pedido_venda_itens_cache');
        Schema::connection('sqlite_local')->dropIfExists('pedidos_venda_cache');
    }
};
