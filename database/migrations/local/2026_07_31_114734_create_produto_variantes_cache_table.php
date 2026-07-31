<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::create('produto_variantes_cache', function (Blueprint $table) {
        $table->unsignedBigInteger('id')->primary();
        $table->unsignedBigInteger('produto_id');
        $table->string('cor')->nullable();
        $table->string('tamanho')->nullable();
        $table->integer('estoque')->default(0);
        $table->timestamps();

        $table->index('produto_id');
    });
}

public function down(): void
{
    Schema::dropIfExists('produto_variantes_cache');
}
};
