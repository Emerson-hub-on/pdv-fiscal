<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            // Remove os campos antigos de texto
            $table->dropColumn(['categoria', 'marca', 'grupo', 'ncm']);

            // Adiciona as FKs pra as novas tabelas
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->foreignId('marca_id')->nullable()->constrained('marcas')->nullOnDelete();
            $table->foreignId('grupo_id')->nullable()->constrained('grupos')->nullOnDelete();
            $table->foreignId('ncm_id')->nullable()->constrained('ncms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropForeign(['categoria_id', 'marca_id', 'grupo_id', 'ncm_id']);
            $table->dropColumn(['categoria_id', 'marca_id', 'grupo_id', 'ncm_id']);

            $table->string('categoria')->nullable();
            $table->string('marca')->nullable();
            $table->string('grupo')->nullable();
            $table->string('ncm', 8);
        });
    }
};
