<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tipos de conta (Aluguel, Energia, Fornecedor...) — cadastrável pelo operador no próprio formulário
        Schema::create('tipos_conta_pagar', function (Blueprint $t) {
            $t->id();
            $t->string('descricao', 60)->unique();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });

        Schema::create('contas_pagar', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tipo_conta_pagar_id')->constrained('tipos_conta_pagar');
            $t->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->nullOnDelete();

            // vínculo futuro: gerar a conta automaticamente ao finalizar uma entrada de nota
            $t->unsignedBigInteger('entrada_nota_id')->nullable()->index();

            $t->string('descricao', 150);
            $t->string('numero_documento', 30)->nullable();
            $t->date('data_conta');
            $t->decimal('valor_total', 12, 2);
            $t->text('observacao')->nullable();
            $t->string('status', 10)->default('aberta'); // aberta | paga
            $t->timestamps();

            $t->index('status');
            $t->index('data_conta');
        });

        Schema::create('contas_pagar_parcelas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conta_pagar_id')->constrained('contas_pagar')->cascadeOnDelete();
            $t->unsignedSmallInteger('numero');
            $t->date('vencimento');
            $t->decimal('valor', 12, 2);

            // preenchidos na baixa (pagamento) da parcela
            $t->date('data_pagamento')->nullable();
            $t->decimal('valor_pago', 12, 2)->nullable();
            $t->unsignedBigInteger('forma_pagamento_id')->nullable();

            $t->timestamps();

            $t->index(['vencimento', 'data_pagamento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contas_pagar_parcelas');
        Schema::dropIfExists('contas_pagar');
        Schema::dropIfExists('tipos_conta_pagar');
    }
};
