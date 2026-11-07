<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categorias do cardápio das comandas (Bebidas, Lanches, Sobremesas...): é por elas que o garçom
 * vai navegar. São separadas da categoria do cadastro do produto (que serve a estoque e relatórios),
 * então organizar o cardápio não altera o produto. Excluir a categoria deixa os itens sem categoria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cardapio_categorias', function (Blueprint $t) {
            $t->id();
            $t->string('nome', 60)->unique();
            $t->unsignedSmallInteger('ordem')->default(0);
            $t->timestamps();
        });

        Schema::table('cardapio_itens', function (Blueprint $t) {
            $t->foreignId('cardapio_categoria_id')->nullable()->after('produto_variante_id')
                ->constrained('cardapio_categorias')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cardapio_itens', function (Blueprint $t) {
            $t->dropConstrainedForeignId('cardapio_categoria_id');
        });

        Schema::dropIfExists('cardapio_categorias');
    }
};
