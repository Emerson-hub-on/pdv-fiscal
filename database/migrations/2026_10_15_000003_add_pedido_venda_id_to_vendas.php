<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MySQL CENTRAL: guarda de qual pedido de venda a venda do caixa nasceu. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->foreignId('pedido_venda_id')->nullable()->after('cliente_id')
                ->constrained('pedidos_venda')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pedido_venda_id');
        });
    }
};
