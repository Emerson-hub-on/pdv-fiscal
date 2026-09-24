<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inutilizacoes_nfe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serie_nfe_id')->constrained('series_nfe');
            $table->unsignedInteger('serie');
            $table->unsignedBigInteger('numero_inicial');
            $table->unsignedBigInteger('numero_final');
            $table->string('justificativa');
            $table->enum('status', ['sucesso', 'erro'])->default('sucesso');
            $table->string('protocolo')->nullable();
            $table->string('motivo')->nullable();
            $table->foreignId('operador_id')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inutilizacoes_nfe');
    }
};