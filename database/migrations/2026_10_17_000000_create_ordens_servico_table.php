<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sem foreign keys para clientes/users/formas de pagamento: não sei os nomes exatos das
        // tabelas no seu banco. Se quiser, troque por ->constrained('nome_da_tabela').
        Schema::create('ordens_servico', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('numero')->unique();

            // Cabeçalho
            $t->date('data_abertura');
            $t->date('previsao_entrega')->nullable();
            $t->unsignedBigInteger('cliente_id')->index();
            $t->unsignedBigInteger('operador_id')->index();
            $t->unsignedBigInteger('tecnico_id')->nullable()->index();
            $t->unsignedBigInteger('forma_pagamento_id')->nullable();
            $t->string('status', 25)->default('aberta')->index();
            $t->string('prioridade', 10)->default('normal');

            // Equipamento
            $t->string('equipamento', 150);
            $t->string('marca', 80)->nullable();
            $t->string('modelo', 80)->nullable();
            $t->string('numero_serie', 80)->nullable();
            $t->string('acessorios', 255)->nullable();

            // Descrições
            $t->text('defeito_relatado');
            $t->text('condicao_entrada')->nullable();
            $t->text('laudo_tecnico')->nullable();
            $t->text('servico_executado')->nullable();
            $t->text('observacoes')->nullable();

            // Valores
            $t->unsignedSmallInteger('garantia_dias')->nullable();
            $t->decimal('valor_servico', 12, 2)->default(0);
            $t->decimal('desconto', 12, 2)->default(0);
            $t->decimal('valor_total', 12, 2)->default(0);

            $t->timestamp('concluida_em')->nullable();
            $t->timestamp('entregue_em')->nullable();
            $t->timestamps();
        });

        Schema::create('ordem_servico_itens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ordem_servico_id')->constrained('ordens_servico')->cascadeOnDelete();
            $t->unsignedBigInteger('produto_id')->nullable();
            $t->unsignedBigInteger('produto_variante_id')->nullable();
            $t->string('codigo_barras', 30)->nullable();
            $t->string('descricao', 255);
            $t->string('unidade', 6)->default('UN');
            $t->decimal('quantidade', 12, 3);
            $t->decimal('valor_unitario', 12, 2);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordem_servico_itens');
        Schema::dropIfExists('ordens_servico');
    }
};
