<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ncms', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 8)->unique(); // ex: 04032000
            $table->string('descricao');
            $table->boolean('cadastro_avulso')->default(false); // true = cadastrado manualmente pelo usuario
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ncms');
    }
};
