<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mesas das comandas (Comandas → Mesas → Lista de Mesas). Só o painel cadastra; o garçom
 * escolhe a mesa e muda a situação dela.
 * - status: disponivel | aguardando (aguardando pedido) | entregue (pedido entregue)
 * - status_desde: quando a mesa entrou na situação atual (é daqui que sai o tempo de espera)
 * - status_por: quem mudou por último (garçom ou usuário do painel)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mesas', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('numero')->unique();
            $t->string('nome', 60)->nullable();
            $t->unsignedTinyInteger('lugares')->nullable();
            $t->boolean('ativo')->default(true);
            $t->string('status', 20)->default('disponivel');
            $t->timestamp('status_desde')->nullable();
            $t->foreignId('status_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mesas');
    }
};
