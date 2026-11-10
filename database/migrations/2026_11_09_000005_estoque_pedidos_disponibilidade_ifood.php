<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estoque dos pedidos (mesa e iFood) e disponibilidade dos itens no iFood:
 *  - comanda_pedidos: quando o estoque do pedido foi baixado (mesa: ao enviar; iFood: ao aceitar) e devolvido (cancelamento);
 *  - ifood_produtos: pausar o item no iFood quando o estoque do produto ligado acabar (e voltar quando repor).
 *    "pausado_automatico" separa a pausa do sistema (volta sozinha) da pausa feita à mão (só volta à mão).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comanda_pedidos', function (Blueprint $table) {
            $table->timestamp('estoque_baixado_em')->nullable()->after('cancelado_em');
            $table->timestamp('estoque_devolvido_em')->nullable()->after('estoque_baixado_em');
        });

        Schema::table('ifood_produtos', function (Blueprint $table) {
            $table->boolean('pausar_sem_estoque')->default(false)->after('status');
            $table->boolean('pausado_automatico')->default(false)->after('pausar_sem_estoque');
            $table->string('disponibilidade_erro', 500)->nullable()->after('pausado_automatico');
            $table->timestamp('disponibilidade_erro_em')->nullable()->after('disponibilidade_erro');
        });
    }

    public function down(): void
    {
        Schema::table('comanda_pedidos', function (Blueprint $table) {
            $table->dropColumn(['estoque_baixado_em', 'estoque_devolvido_em']);
        });

        Schema::table('ifood_produtos', function (Blueprint $table) {
            $table->dropColumn(['pausar_sem_estoque', 'pausado_automatico', 'disponibilidade_erro', 'disponibilidade_erro_em']);
        });
    }
};
