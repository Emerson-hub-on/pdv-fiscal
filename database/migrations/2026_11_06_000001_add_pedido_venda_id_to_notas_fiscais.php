<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Montagem NF-e a partir de pedido de venda: a nota guarda o pedido de origem
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->foreignId('pedido_venda_id')->nullable()->after('venda_id')
                ->constrained('pedidos_venda')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pedido_venda_id');
        });
    }
};
