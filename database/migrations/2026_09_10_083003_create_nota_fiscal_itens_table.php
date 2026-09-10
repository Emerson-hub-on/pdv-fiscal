<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nota_fiscal_itens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('nota_fiscal_id')->constrained('notas_fiscais')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos');

            $table->string('cfop', 4);
            $table->foreignId('ncm_id')->nullable()->constrained('ncms');
            $table->foreignId('cest_id')->nullable()->constrained('cests');
            $table->foreignId('class_trib_ibs_cbs_id')->nullable()->constrained('classificacoes_tributarias');
            $table->foreignId('tributacao_id')->nullable()->constrained('tributacoes');
            $table->foreignId('pis_cofins_id')->nullable()->constrained('classificacoes_pis_cofins');
            $table->foreignId('ipi_id')->nullable()->constrained('classificacoes_ipi');

            $table->decimal('quantidade', 12, 3);
            $table->decimal('valor_unitario', 12, 4);
            $table->decimal('valor_desconto', 12, 2)->default(0);
            $table->decimal('valor_total', 12, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_fiscal_itens');
    }
};