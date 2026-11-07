<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comandas → Cardápio → Menu: os produtos que o garçom vê na hora de fazer o pedido.
 * Nome, preço e estoque vêm sempre do produto (não duplica); aqui ficam só a foto, a descrição
 * mostrada no cardápio, a ordem e se o item aparece para o garçom.
 * - imagem: caminho no disco "local" (storage/app/private/cardapio/...), servida pela rota cardapio.imagem
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cardapio_itens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('produto_id')->unique()->constrained('produtos')->cascadeOnDelete();
            $t->string('imagem')->nullable();
            $t->text('descricao')->nullable();
            $t->boolean('ativo')->default(true);
            $t->unsignedSmallInteger('ordem')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cardapio_itens');
    }
};
