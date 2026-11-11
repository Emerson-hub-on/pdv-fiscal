<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido do iFood: CPF e endereço do cliente informados pelo operador para a NFC-e de entrega em domicílio
 * (indPres 4 exige destinatário com CPF e endereço; o iFood quase nunca manda o CPF).
 * JSON com cpf, nome, logradouro, numero, complemento, bairro, cep, cidade, uf. Vale sobre os dados do payload.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ifood_pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('ifood_pedidos', 'dados_nfce')) {
                $table->json('dados_nfce')->nullable()->after('disputa');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ifood_pedidos', function (Blueprint $table) {
            $table->dropColumn('dados_nfce');
        });
    }
};
