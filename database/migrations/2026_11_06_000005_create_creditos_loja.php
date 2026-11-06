<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Crédito em loja (haver do cliente / vale-troca):
 *  - creditos_loja: um crédito por NF-e de entrada de troca autorizada (ou lançado à parte), com saldo;
 *  - credito_loja_movimentos: histórico (crédito, uso em NF-e ou no caixa, estorno, cancelamento);
 *  - notas_fiscais.valor_credito_loja: quanto da NF-e de venda foi pago com crédito em loja (tPag 21);
 *  - venda_pagamentos aceita a forma "credito_loja" e guarda qual crédito foi usado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creditos_loja', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 12)->unique();                 // vale-troca (VT000123)
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('nota_fiscal_id')->nullable()->unique()->constrained('notas_fiscais')->nullOnDelete();
            $table->decimal('valor', 12, 2);
            $table->decimal('saldo', 12, 2);
            $table->string('status', 12)->default('ativo')->index();  // ativo | esgotado | cancelado
            $table->string('observacao', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('credito_loja_movimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_loja_id')->constrained('creditos_loja')->cascadeOnDelete();
            $table->string('tipo', 12);                               // credito | uso | estorno | cancelamento
            $table->decimal('valor', 12, 2);
            $table->foreignId('nota_fiscal_id')->nullable()->constrained('notas_fiscais')->nullOnDelete();
            $table->string('venda_uuid', 36)->nullable()->index();   // uso numa venda do caixa
            $table->string('descricao', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['credito_loja_id', 'tipo', 'venda_uuid'], 'credito_mov_venda_unico');
        });

        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->decimal('valor_credito_loja', 12, 2)->default(0)->after('valor_total');
        });

        Schema::table('venda_pagamentos', function (Blueprint $table) {
            $table->string('forma_pagamento', 20)->change();
            $table->foreignId('credito_loja_id')->nullable()->after('valor')->constrained('creditos_loja')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('venda_pagamentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credito_loja_id');
        });
        DB::table('venda_pagamentos')->where('forma_pagamento', 'credito_loja')->update(['forma_pagamento' => 'dinheiro']);
        Schema::table('venda_pagamentos', function (Blueprint $table) {
            $table->enum('forma_pagamento', ['dinheiro', 'pix', 'credito', 'debito'])->change();
        });

        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->dropColumn('valor_credito_loja');
        });

        Schema::dropIfExists('credito_loja_movimentos');
        Schema::dropIfExists('creditos_loja');
    }
};
