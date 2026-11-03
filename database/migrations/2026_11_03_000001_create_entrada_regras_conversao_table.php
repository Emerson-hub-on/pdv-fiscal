<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Regras de conversão criadas pelo usuário em cada operação de entrada:
// CFOP + CST/CSOSN do fornecedor -> CFOP + CST/CSOSN de entrada.
// Têm prioridade sobre as regras automáticas (cfop_entrada_conversoes e cst_entrada_conversoes).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrada_regras_conversao', function (Blueprint $t) {
            $t->id();
            $t->foreignId('operacao_entrada_id')->constrained('operacoes_entrada')->cascadeOnDelete();
            $t->enum('regime', ['simples', 'normal']);       // regime da empresa quando a regra foi criada
            $t->char('cfop_origem', 4);
            $t->enum('tipo_origem', ['cst', 'csosn']);
            $t->string('codigo_origem', 3);
            $t->foreignId('cfop_entrada_id')->constrained('cfops_entrada')->restrictOnDelete();
            $t->string('codigo_entrada', 3);
            $t->boolean('gera_credito')->default(false);
            $t->timestamps();

            // Uma regra só por combinação: o sistema nunca fica com duas respostas para o mesmo item
            $t->unique(['operacao_entrada_id', 'regime', 'cfop_origem', 'tipo_origem', 'codigo_origem'], 'entrada_regras_conversao_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrada_regras_conversao');
    }
};
