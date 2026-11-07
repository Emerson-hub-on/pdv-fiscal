<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cardápio igual ao caixa: produto com variação entra por variante (cada código de barras é um item,
 * com foto própria). produto_variante_id nulo = produto sem variação.
 * A chave única passa a ser (produto_id, produto_variante_id); como o MySQL aceita vários NULL,
 * o "um por produto sem variação" é garantido na validação do CardapioController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cardapio_itens', function (Blueprint $t) {
            $t->foreignId('produto_variante_id')->nullable()->after('produto_id')
                ->constrained('produto_variantes')->cascadeOnDelete();
            // Índice novo antes de tirar o antigo: a FK de produto_id precisa de um índice começando por ela
            $t->unique(['produto_id', 'produto_variante_id'], 'cardapio_itens_produto_variante_unique');
        });

        Schema::table('cardapio_itens', function (Blueprint $t) {
            $t->dropUnique('cardapio_itens_produto_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cardapio_itens', function (Blueprint $t) {
            $t->unique('produto_id');
        });

        Schema::table('cardapio_itens', function (Blueprint $t) {
            $t->dropConstrainedForeignId('produto_variante_id');
            $t->dropUnique('cardapio_itens_produto_variante_unique');
        });
    }
};
