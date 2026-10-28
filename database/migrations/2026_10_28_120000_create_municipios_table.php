<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipios', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_ibge', 7)->unique();
            $table->string('nome', 100);
            $table->string('nome_busca', 100)->index(); // minúsculo e sem acento, para a busca
            $table->string('uf', 2)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipios');
    }
};
