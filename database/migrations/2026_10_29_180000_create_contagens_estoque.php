<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contagem física do estoque importada de planilha. Enquanto rascunho, só mostra o comparativo;
     * ao aplicar, cada diferença vira um ajuste no EstoqueService (com histórico), na data da contagem.
     */
    public function up(): void
    {
        Schema::create('contagens_estoque', function (Blueprint $table) {
            $table->id();
            $table->date('data');                              // dia da contagem (os ajustes entram no fim deste dia)
            $table->string('status', 20)->default('rascunho'); // rascunho | aplicada
            $table->string('arquivo_nome')->nullable();
            $table->string('observacao')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aplicada_em')->nullable();
            $table->foreignId('aplicada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('contagem_estoque_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contagem_estoque_id')->constrained('contagens_estoque')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos');
            $table->foreignId('produto_variante_id')->nullable()->constrained('produto_variantes')->nullOnDelete();
            $table->string('descricao');
            $table->string('unidade', 6);
            $table->decimal('saldo_sistema', 15, 3);      // saldo no fim do dia da contagem
            $table->decimal('quantidade_contada', 15, 3);
            $table->decimal('diferenca', 15, 3);          // contada − sistema (positivo = sobra)
            $table->decimal('custo_unitario', 15, 6)->default(0);
            $table->decimal('valor_diferenca', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['contagem_estoque_id', 'produto_id', 'produto_variante_id'], 'contagem_item_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contagem_estoque_itens');
        Schema::dropIfExists('contagens_estoque');
    }
};
