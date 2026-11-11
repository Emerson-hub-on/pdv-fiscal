<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conciliação financeira do iFood (Financial API v3).
 *
 * ifood_eventos_financeiros: cada evento financeiro (venda, comissão, taxa, cancelamento, subsídio...). O repasse
 *   líquido de um pedido é a soma de valor dos eventos com impacta_repasse = 1 (regra do iFood). Os eventos não têm id
 *   próprio: a chave é o hash do evento + o número da ocorrência dentro da consulta (eventos iguais repetidos contam).
 * ifood_repasses: itens de fechamento (repasse, boleto, recebíveis, renegociada) e a conta a receber gerada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ifood_eventos_financeiros', function (Blueprint $table) {
            $table->id();
            $table->string('chave', 80)->unique();
            $table->string('merchant_id', 64)->nullable()->index();
            $table->string('nome', 60)->index();               // SALE_CONCLUDED, ORDER_COMMISSION, PAYMENT_TRANSACTION_FEE...
            $table->string('descricao', 200)->nullable();
            $table->string('produto', 30)->nullable();
            $table->string('gatilho', 60)->nullable();         // trigger
            $table->timestamp('ocorrido_em')->nullable()->index(); // dateTime
            $table->string('competencia', 7)->nullable()->index(); // AAAA-MM
            $table->date('periodo_inicio')->nullable();
            $table->date('periodo_fim')->nullable();
            $table->string('referencia_tipo', 30)->nullable();
            $table->string('referencia_id', 64)->nullable()->index(); // id do pedido no iFood (ORDER)
            $table->timestamp('referencia_em')->nullable();
            $table->boolean('impacta_repasse')->default(true);
            $table->decimal('valor', 12, 2);
            $table->decimal('base_calculo', 12, 2)->nullable();
            $table->decimal('percentual', 8, 4)->nullable();
            $table->date('previsao_repasse')->nullable()->index();
            $table->string('pagamento_metodo', 30)->nullable();
            $table->string('pagamento_bandeira', 40)->nullable();
            $table->string('responsavel', 30)->nullable();      // liability
            $table->json('payload');
            $table->timestamps();
        });

        Schema::create('ifood_repasses', function (Blueprint $table) {
            $table->id();
            $table->string('item_id', 64)->unique();           // closingItems.id
            $table->string('merchant_id', 64)->nullable();
            $table->string('tipo', 30);                        // REPASSE | BOLETO | REGISTRO_RECEBIVEIS | RENEGOCIADA
            $table->string('produto', 30)->nullable();
            $table->string('status', 40)->nullable();          // SUCCEED | PENDING | FAILED | TRANSFER_RENEGOTIATED
            $table->decimal('valor', 12, 2);
            $table->date('data_pagamento')->nullable()->index();
            $table->date('apuracao_inicio')->nullable();
            $table->date('apuracao_fim')->nullable();
            $table->string('transacao_id', 64)->nullable();
            $table->string('banco', 80)->nullable();           // só o nome do banco (agência/conta não são gravadas)
            $table->unsignedBigInteger('conta_receber_id')->nullable()->index();
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ifood_repasses');
        Schema::dropIfExists('ifood_eventos_financeiros');
    }
};
