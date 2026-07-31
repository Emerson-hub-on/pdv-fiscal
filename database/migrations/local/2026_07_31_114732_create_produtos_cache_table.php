<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::create('produtos_cache', function (Blueprint $table) {
        $table->unsignedBigInteger('id')->primary(); // mesmo id do MySQL central
        $table->string('nome');
        $table->string('codigo_interno');
        $table->string('codigo_barras')->nullable();
        $table->string('ncm', 8);
        $table->string('cest', 7)->nullable();
        $table->string('cfop_padrao', 4);
        $table->string('unidade_comercial', 6);
        $table->string('unidade_tributavel', 6);
        $table->unsignedTinyInteger('origem_mercadoria')->default(0);
        $table->string('csosn', 3);
        $table->string('class_trib_ibs_cbs', 6)->nullable();
        $table->decimal('preco_venda', 12, 2);
        $table->boolean('tem_variacao')->default(false);
        $table->integer('estoque')->default(0);
        $table->boolean('ativo')->default(true);
        $table->timestamp('atualizado_em_origem')->nullable(); // updated_at do MySQL central
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('produtos_cache');
}
};
