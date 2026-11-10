<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eventos recebidos no polling do iFood. A homologação exige confirmar (acknowledgment) TODOS os eventos logo
 * depois do polling: por isso cada evento é gravado aqui antes da confirmação e processado a partir daqui.
 * O que falhar fica com processado_em nulo e é tentado de novo (proxima_tentativa_em), sem depender do iFood reenviar.
 * O evento_id único também impede processar duas vezes um evento que o iFood mande repetido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ifood_eventos', function (Blueprint $table) {
            $table->id();
            $table->string('evento_id', 64)->unique();
            $table->string('order_id', 64)->nullable()->index();
            $table->string('codigo', 40)->nullable();
            $table->json('payload');
            $table->timestamp('criado_ifood_em', 3)->nullable(); // com milissegundos: eventos do mesmo pedido no mesmo segundo
            $table->timestamp('processado_em')->nullable();
            $table->unsignedSmallInteger('tentativas')->default(0);
            $table->string('ultimo_erro', 500)->nullable();
            $table->timestamp('proxima_tentativa_em')->nullable();
            $table->timestamp('desistido_em')->nullable(); // passou do limite de tentativas: fica só registrado
            $table->timestamps();

            $table->index(['processado_em', 'desistido_em', 'proxima_tentativa_em'], 'ifood_eventos_fila');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ifood_eventos');
    }
};
