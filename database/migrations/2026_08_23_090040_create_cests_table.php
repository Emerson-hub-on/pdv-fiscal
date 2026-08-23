<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cests', function (Blueprint $table) {
            $table->id();
            // Código sem pontuação, ex: "0100100" (formato oficial: SS.III.DD)
            $table->string('codigo', 7)->unique();
            $table->string('descricao');
            // Segmento (2 primeiros dígitos) e descrição do segmento, úteis para filtro/agrupamento
            $table->string('segmento_codigo', 2)->nullable()->index();
            $table->string('segmento_descricao')->nullable();
            $table->timestamps();
        });

        // Tabela de relação CEST x NCM (um CEST pode valer para vários NCMs e vice-versa)
        Schema::create('cest_ncm', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cest_id')->constrained('cests')->cascadeOnDelete();
            $table->string('ncm', 8)->index();
            $table->timestamps();

            $table->unique(['cest_id', 'ncm']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cest_ncm');
        Schema::dropIfExists('cests');
    }
};