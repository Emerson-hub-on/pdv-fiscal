<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conta de mesa: CPF (e nome) do cliente para a NFC-e, informado pelo operador em Pedidos Restaurante
 * quando o valor passa do limite que exige o CPF (config fiscal.cpf_obrigatorio_acima). JSON com cpf e nome.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comandas', function (Blueprint $table) {
            if (!Schema::hasColumn('comandas', 'dados_nfce')) {
                $table->json('dados_nfce')->nullable()->after('venda_reservada_em');
            }
        });
    }

    public function down(): void
    {
        Schema::table('comandas', function (Blueprint $table) {
            $table->dropColumn('dados_nfce');
        });
    }
};
