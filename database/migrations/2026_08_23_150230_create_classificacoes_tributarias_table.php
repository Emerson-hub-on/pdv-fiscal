<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classificacoes_tributarias', function (Blueprint $table) {
            $table->id();
            // cClassTrib: 6 dígitos, ex: "200001"
            $table->string('codigo', 6)->unique();
            $table->text('descricao');
            // CST IBS/CBS: 3 dígitos, ex: "200" — sempre acompanha o cClassTrib
            $table->string('cst_codigo', 3)->index();
            $table->string('cst_descricao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classificacoes_tributarias');
    }
};