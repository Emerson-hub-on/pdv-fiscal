<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            // Nullable de proposito - a esmagadora maioria das vendas de NFC-e
            // segue sem identificar o consumidor. So preenche quando o operador
            // usa o botao "Adicionar consumidor" na tela de pagamento.
            $table->foreignId('cliente_id')->nullable()->constrained('clientes');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cliente_id');
        });
    }
};
