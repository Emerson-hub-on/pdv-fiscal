<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->foreignId('veiculo_id')
                ->nullable()
                ->after('transportador_id')
                ->constrained('veiculos')
                ->nullOnDelete();
            
            $table->unsignedInteger('vol_quantidade')->nullable();
            $table->string('vol_especie', 60)->nullable();
            $table->string('vol_marca', 60)->nullable();
            $table->string('vol_numeracao', 60)->nullable();
            $table->decimal('vol_peso_liquido', 15, 3)->nullable();
            $table->decimal('vol_peso_bruto', 15, 3)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            // Remove a chave estrangeira primeiro
            $table->dropForeign(['veiculo_id']);
            
            // Remove todas as colunas criadas
            $table->dropColumn([
                'veiculo_id',
                'vol_quantidade',
                'vol_especie',
                'vol_marca',
                'vol_numeracao',
                'vol_peso_liquido',
                'vol_peso_bruto'
            ]);
        });
    }
};
