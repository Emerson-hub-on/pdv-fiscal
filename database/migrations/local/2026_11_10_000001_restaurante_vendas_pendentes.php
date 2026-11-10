<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite do caixa: venda que veio de uma conta de mesa ou de um pedido do iFood (Pedidos Restaurante).
 * O estoque dessas vendas já foi baixado pelo pedido, então o caixa e o servidor não baixam de novo.
 * ind_pres e o intermediador (iFood) vão para a NFC-e.
 */
return new class extends Migration
{
    protected $connection = 'sqlite_local';

    public function up(): void
    {
        Schema::connection('sqlite_local')->table('vendas_pendentes', function (Blueprint $table) {
            $table->string('restaurante_origem', 10)->nullable();      // mesa | ifood
            $table->unsignedBigInteger('restaurante_ref_id')->nullable(); // id da comanda (mesa) ou do comanda_pedido (iFood)
            $table->string('restaurante_rotulo', 60)->nullable();      // "Mesa 4", "iFood #1234"
            $table->unsignedTinyInteger('ind_pres')->nullable();       // 1 presencial, 4 entrega em domicílio
            $table->string('intermediador_cnpj', 14)->nullable();
            $table->string('intermediador_id', 60)->nullable();        // identificação da loja no intermediador
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->table('vendas_pendentes', function (Blueprint $table) {
            $table->dropColumn(['restaurante_origem', 'restaurante_ref_id', 'restaurante_rotulo', 'ind_pres', 'intermediador_cnpj', 'intermediador_id']);
        });
    }
};
