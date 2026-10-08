<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlite_local')->create('inutilizacoes_local', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->unsignedBigInteger('pdv_id');
            $t->string('serie', 10);
            $t->unsignedInteger('numero_inicial');
            $t->unsignedInteger('numero_final');
            $t->text('justificativa');
            $t->string('status', 20); // sucesso | erro
            $t->string('protocolo', 60)->nullable();
            $t->text('motivo')->nullable();
            $t->unsignedBigInteger('operador_id')->nullable();
            $t->boolean('sync_pendente')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->dropIfExists('inutilizacoes_local');
    }
};