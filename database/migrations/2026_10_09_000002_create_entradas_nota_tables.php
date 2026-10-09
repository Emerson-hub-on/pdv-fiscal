<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entradas_nota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fornecedor_id')->constrained('fornecedores');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('tipo_entrada', 10)->default('manual');      // manual | xml (etapa futura)
            $table->string('status', 12)->default('rascunho');          // rascunho | finalizada | cancelada

            $table->string('chave_acesso', 44)->nullable()->unique();
            $table->string('modelo', 2)->default('55');
            $table->string('serie', 3)->nullable();
            $table->string('numero', 9);
            $table->date('data_emissao');
            $table->date('data_entrada');
            $table->string('natureza_operacao', 60)->nullable();

            $table->decimal('valor_produtos', 14, 2)->default(0);
            $table->decimal('valor_frete', 14, 2)->default(0);
            $table->decimal('valor_desconto', 14, 2)->default(0);
            $table->decimal('valor_outras', 14, 2)->default(0);
            $table->decimal('valor_total', 14, 2)->default(0);

            $table->boolean('atualizar_custo')->default(true);
            $table->text('observacao')->nullable();
            $table->timestamp('finalizada_em')->nullable();
            $table->timestamps();

            $table->index(['fornecedor_id', 'modelo', 'serie', 'numero']);
            $table->index(['status', 'data_entrada']);
        });

        Schema::create('entrada_nota_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrada_nota_id')->constrained('entradas_nota')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos');
            // Reservado para a etapa de variações (sem uso por enquanto)
            $table->unsignedBigInteger('produto_variante_id')->nullable();

            $table->string('codigo_fornecedor', 60)->nullable();
            $table->string('descricao', 200);
            $table->string('unidade', 6)->nullable();

            $table->decimal('quantidade', 12, 3);
            $table->decimal('valor_unitario', 14, 4);
            $table->decimal('valor_desconto', 14, 2)->default(0);
            $table->decimal('valor_total', 14, 2);

            $table->string('lote', 30)->nullable();
            $table->date('validade')->nullable();
            $table->timestamps();
        });

        Schema::create('produto_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos');
            $table->unsignedBigInteger('produto_variante_id')->nullable();
            $table->foreignId('entrada_nota_item_id')->nullable()->constrained('entrada_nota_itens')->nullOnDelete();

            $table->string('lote', 30)->nullable();
            $table->date('validade')->nullable();
            $table->decimal('quantidade_inicial', 12, 3);
            $table->decimal('quantidade_atual', 12, 3);
            $table->timestamps();

            $table->index(['produto_id', 'validade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_lotes');
        Schema::dropIfExists('entrada_nota_itens');
        Schema::dropIfExists('entradas_nota');
    }
};
