<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::create('produtos', function (Blueprint $table) {
        $table->id();
        $table->string('nome');
        $table->text('descricao')->nullable();

        // Campos livres de gestao
        $table->string('categoria')->nullable();
        $table->string('marca')->nullable();
        $table->string('grupo')->nullable();

        // Codificacao
        $table->string('codigo_interno')->unique(); // cProd
        $table->string('codigo_barras')->nullable(); // cEAN, "SEM GTIN" se nao tiver

        // Fiscal - obrigatorios pra emissao
        $table->string('ncm', 8);
        $table->string('cest', 7)->nullable(); // so obrigatorio se NCM exigir ICMS-ST
        $table->string('cfop_padrao', 4);
        $table->string('unidade_comercial', 6); // uCom - ex: UN, KG, CX
        $table->string('unidade_tributavel', 6); // uTrib
        $table->unsignedTinyInteger('origem_mercadoria')->default(0); // orig
        $table->string('csosn', 3); // Simples Nacional
        $table->string('class_trib_ibs_cbs', 6)->nullable(); // cClassTrib - Reforma Tributaria

        // Precos
        $table->decimal('preco_venda', 12, 2);
        $table->decimal('preco_custo', 12, 2)->nullable();

        // Estoque
        $table->boolean('tem_variacao')->default(false);
        $table->integer('estoque')->default(0); // usado so quando tem_variacao = false
        $table->integer('estoque_minimo')->default(0);

        $table->boolean('ativo')->default(true);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('produtos');
}
};
