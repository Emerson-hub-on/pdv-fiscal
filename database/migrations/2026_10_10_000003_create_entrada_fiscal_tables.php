<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfops_entrada', function (Blueprint $t) {
            $t->id();
            $t->char('codigo', 4)->unique();
            $t->string('descricao');
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });

        Schema::create('operacoes_entrada', function (Blueprint $t) {
            $t->id();
            $t->string('codigo', 40)->unique();
            $t->string('descricao');
            $t->boolean('movimenta_estoque')->default(true);
            $t->unsignedSmallInteger('ordem')->default(0);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });

        Schema::create('cfop_entrada_conversoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('operacao_entrada_id')->constrained('operacoes_entrada')->cascadeOnDelete();
            $t->char('cfop_origem', 4);
            $t->foreignId('cfop_entrada_id')->constrained('cfops_entrada')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['operacao_entrada_id', 'cfop_origem']);
        });

        Schema::create('cst_entrada_conversoes', function (Blueprint $t) {
            $t->id();
            $t->enum('regime', ['simples', 'normal']);
            $t->enum('tipo_origem', ['cst', 'csosn']);
            $t->string('codigo_origem', 3);
            $t->string('codigo_entrada', 3);
            $t->boolean('gera_credito')->default(false);
            $t->timestamps();
            $t->unique(['regime', 'tipo_origem', 'codigo_origem']);
        });

        Schema::table('entradas_nota', function (Blueprint $t) {
            $t->foreignId('operacao_entrada_id')->nullable()->constrained('operacoes_entrada')->nullOnDelete();
        });

        // Retrato fiscal do item: o que veio no XML e o que foi convertido
        Schema::table('entrada_nota_itens', function (Blueprint $t) {
            $t->char('cfop_origem', 4)->nullable();
            $t->string('cst_origem', 3)->nullable();
            $t->string('csosn_origem', 3)->nullable();
            $t->unsignedTinyInteger('origem_mercadoria')->nullable();
            $t->foreignId('cfop_entrada_id')->nullable()->constrained('cfops_entrada')->nullOnDelete();
            $t->string('cst_csosn_entrada', 3)->nullable();
            $t->boolean('gera_credito')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('entrada_nota_itens', function (Blueprint $t) {
            $t->dropConstrainedForeignId('cfop_entrada_id');
            $t->dropColumn(['cfop_origem', 'cst_origem', 'csosn_origem', 'origem_mercadoria', 'cst_csosn_entrada', 'gera_credito']);
        });
        Schema::table('entradas_nota', fn (Blueprint $t) => $t->dropConstrainedForeignId('operacao_entrada_id'));
        Schema::dropIfExists('cst_entrada_conversoes');
        Schema::dropIfExists('cfop_entrada_conversoes');
        Schema::dropIfExists('operacoes_entrada');
        Schema::dropIfExists('cfops_entrada');
    }
};