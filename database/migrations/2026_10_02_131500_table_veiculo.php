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
        Schema::create('veiculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transportador_id')->nullable()->constrained('transportadores')->nullOnDelete();
            $table->string('placa', 7)->unique();   // só letras e números: ABC1234 ou ABC1D23 (Mercosul)
            $table->char('uf', 2);                  // UF onde o veículo está emplacado
            $table->string('rntrc', 8)->nullable(); // Registro Nacional de Transportadores Rodoviários de Carga (ANTT)
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('veiculos');
    }
};
