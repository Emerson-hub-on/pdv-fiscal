<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inutilizacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdv_id')->constrained('pdvs');
            $table->unsignedInteger('serie');
            $table->unsignedBigInteger('numero_inicial');
            $table->unsignedBigInteger('numero_final');
            $table->text('justificativa');
            $table->enum('status', ['sucesso', 'erro'])->default('erro');
            $table->string('protocolo')->nullable();
            $table->text('motivo')->nullable();
            $table->foreignId('operador_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inutilizacoes');
    }
};
