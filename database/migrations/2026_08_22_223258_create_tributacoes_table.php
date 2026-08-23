<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tributacoes', function (Blueprint $table) {
            $table->id();
            $table->string('descricao');
            $table->unsignedTinyInteger('crt'); // 1, 2 ou 3
            $table->string('cfop', 4);
            $table->string('csosn', 3)->nullable(); // Simples Nacional
            $table->string('cst_icms', 3)->nullable(); // Lucro Presumido / Real
            $table->decimal('aliquota_icms', 5, 2)->default(0);
            $table->text('observacao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // FK na tabela produtos
        Schema::table('produtos', function (Blueprint $table) {
            $table->foreignId('tributacao_id')->nullable()->after('ncm_id')->constrained('tributacoes')->nullOnDelete();
            // Remove os campos antigos (que viravam fixos no XML)
            $table->dropColumn(['cfop_padrao', 'csosn']);
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropForeign(['tributacao_id']);
            $table->dropColumn('tributacao_id');
            $table->string('cfop_padrao', 4)->nullable();
            $table->string('csosn', 3)->nullable();
        });

        Schema::dropIfExists('tributacoes');
    }
};
