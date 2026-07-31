<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::create('venda_itens', function (Blueprint $table) {
        $table->id();
        $table->foreignId('venda_id')->constrained('vendas')->cascadeOnDelete();
        $table->foreignId('produto_id')->constrained('produtos');
        $table->foreignId('produto_variante_id')->nullable()->constrained('produto_variantes');
        $table->integer('quantidade');
        $table->decimal('preco_unitario', 12, 2);
        $table->decimal('subtotal', 12, 2);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('venda_itens');
}
};
