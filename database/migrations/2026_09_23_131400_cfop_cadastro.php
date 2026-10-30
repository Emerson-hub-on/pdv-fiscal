<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfop_saida', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 4)->unique();
            $table->string('descricao');
            $table->boolean('movimenta_estoque')->default(true);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfop_saida');
    }
};