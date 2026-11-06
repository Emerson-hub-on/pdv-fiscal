<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baixa do convênio feita no caixa: a parcela guarda de qual caixa veio o recebimento, a forma usada lá
 * (para o fechamento conferir a gaveta) e o uuid do recebimento (sincronização idempotente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contas_receber_parcelas', function (Blueprint $t) {
            $t->string('recebimento_uuid', 36)->nullable()->after('forma_pagamento_id')->index();
            $t->string('recebido_caixa_uuid', 36)->nullable()->after('recebimento_uuid')->index();
            $t->string('recebido_forma_caixa', 20)->nullable()->after('recebido_caixa_uuid');
            $t->unsignedBigInteger('recebido_por_id')->nullable()->after('recebido_forma_caixa');
        });
    }

    public function down(): void
    {
        Schema::table('contas_receber_parcelas', fn (Blueprint $t) => $t->dropColumn([
            'recebimento_uuid', 'recebido_caixa_uuid', 'recebido_forma_caixa', 'recebido_por_id',
        ]));
    }
};
