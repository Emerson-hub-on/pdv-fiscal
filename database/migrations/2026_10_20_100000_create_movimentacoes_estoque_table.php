<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Livro de movimentações do estoque: cada linha é uma alteração do saldo
 * (produtos.estoque / produto_variantes.estoque), com quem, quando, por quê e
 * o saldo antes/depois. Só recebe inserções — nunca é editado nem apagado.
 *
 * Sem foreign key em produto_variante_id: o cadastro permite apagar variações,
 * e o histórico precisa sobreviver a isso (por isso também guarda o texto da
 * variação em "variacao").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimentacoes_estoque', function (Blueprint $table) {
            $table->id();

            $table->foreignId('produto_id')->constrained('produtos');
            $table->unsignedBigInteger('produto_variante_id')->nullable();
            $table->string('variacao', 100)->nullable();

            $table->string('tipo', 30);

            // Com sinal: negativo = saída, positivo = entrada
            $table->decimal('quantidade', 12, 3);
            $table->decimal('saldo_anterior', 12, 3);
            $table->decimal('saldo_posterior', 12, 3);

            // Custo do produto no momento da movimentação (para valorizar as saídas)
            $table->decimal('custo_unitario', 12, 4)->nullable();

            // Documento que originou a movimentação (nota fiscal, venda...)
            $table->string('documento_tipo', 30)->nullable();
            $table->string('documento_id', 64)->nullable();
            $table->string('documento_rotulo', 100)->nullable();

            $table->string('observacao')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
            $table->index('tipo');
            $table->index(['produto_id', 'produto_variante_id', 'created_at'], 'mov_estoque_produto_idx');
            $table->index(['documento_tipo', 'documento_id'], 'mov_estoque_documento_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentacoes_estoque');
    }
};
