<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::create('produto_variantes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
        $table->string('cor')->nullable();
        $table->string('tamanho')->nullable();
        $table->string('sku')->nullable();
        $table->integer('estoque')->default(0);
        $table->integer('estoque_minimo')->default(0);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('produto_variantes');
}
};
