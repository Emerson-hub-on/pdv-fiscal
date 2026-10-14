<?php

use App\Models\Cliente;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tipos de conta (Venda a prazo, Serviço, Aluguel recebido...) — cadastrável pelo operador no próprio formulário
        Schema::create('tipos_conta_receber', function (Blueprint $t) {
            $t->id();
            $t->string('descricao', 60)->unique();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });

        Schema::create('contas_receber', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tipo_conta_receber_id')->constrained('tipos_conta_receber');
            $t->foreignId('cliente_id')->nullable()->constrained((new Cliente)->getTable())->nullOnDelete();

            // vínculo futuro: gerar a conta automaticamente numa venda a prazo do PDV
            $t->unsignedBigInteger('venda_id')->nullable()->index();

            $t->string('descricao', 150);
            $t->string('numero_documento', 30)->nullable();
            $t->date('data_conta');
            $t->decimal('valor_total', 12, 2);
            $t->text('observacao')->nullable();
            $t->string('status', 10)->default('aberta'); // aberta | recebida
            $t->timestamps();

            $t->index('status');
            $t->index('data_conta');
        });

        Schema::create('contas_receber_parcelas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conta_receber_id')->constrained('contas_receber')->cascadeOnDelete();
            $t->unsignedSmallInteger('numero');
            $t->date('vencimento');
            $t->decimal('valor', 12, 2);

            // preenchidos na baixa (recebimento) da parcela
            $t->date('data_recebimento')->nullable();
            $t->decimal('valor_recebido', 12, 2)->nullable();
            $t->unsignedBigInteger('forma_pagamento_id')->nullable();

            $t->timestamps();

            $t->index(['vencimento', 'data_recebimento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contas_receber_parcelas');
        Schema::dropIfExists('contas_receber');
        Schema::dropIfExists('tipos_conta_receber');
    }
};
