<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convênio (venda fiado): limite de crédito concedido no cadastro do cliente.
 * A venda paga no convênio vira uma conta a receber (origem "convenio"); o limite usado
 * é a soma das parcelas dessas contas ainda não recebidas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $t) {
            $t->boolean('limite_credito_ativo')->default(false)->after('ativo');
            $t->decimal('limite_credito', 12, 2)->default(0)->after('limite_credito_ativo');
            $t->unsignedSmallInteger('limite_credito_dias')->default(30)->after('limite_credito'); // prazo do vencimento
            $t->string('limite_credito_observacao', 255)->nullable()->after('limite_credito_dias');
        });

        Schema::create('limites_credito_historico', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $t->boolean('ativo');
            $t->decimal('limite_anterior', 12, 2)->default(0);
            $t->decimal('limite_novo', 12, 2)->default(0);
            $t->unsignedSmallInteger('dias')->default(30);
            $t->string('observacao', 255)->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::table('contas_receber', function (Blueprint $t) {
            $t->string('origem', 20)->nullable()->after('venda_id')->index(); // null = lançada à mão | convenio
        });
    }

    public function down(): void
    {
        Schema::table('contas_receber', fn (Blueprint $t) => $t->dropColumn('origem'));
        Schema::dropIfExists('limites_credito_historico');
        Schema::table('clientes', fn (Blueprint $t) => $t->dropColumn([
            'limite_credito_ativo', 'limite_credito', 'limite_credito_dias', 'limite_credito_observacao',
        ]));
    }
};
