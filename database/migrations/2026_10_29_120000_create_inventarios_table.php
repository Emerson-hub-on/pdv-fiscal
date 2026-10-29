<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Inventário para o SPED (Bloco H): uma "foto" do estoque numa data, que não muda depois de fechada
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id();
            $table->date('data');                              // DT_INV
            $table->char('motivo', 2)->default('01');           // MOT_INV
            $table->string('status', 10)->default('rascunho'); // rascunho | fechado
            $table->boolean('incluir_zerados')->default(false);
            $table->decimal('valor_total', 15, 2)->default(0);  // VL_INV
            $table->string('observacao', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fechado_em')->nullable();
            $table->timestamps();

            $table->index(['status', 'data']);
        });

        Schema::create('inventario_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventario_id')->constrained('inventarios')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos');
            $table->string('unidade', 6);
            $table->decimal('quantidade_calculada', 15, 3)->default(0); // o que o histórico apontou
            $table->decimal('quantidade', 15, 3)->default(0);           // o que vai para o arquivo (editável)
            $table->decimal('custo_unitario', 15, 6)->default(0);
            $table->decimal('valor_total', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['inventario_id', 'produto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_itens');
        Schema::dropIfExists('inventarios');
    }
};
