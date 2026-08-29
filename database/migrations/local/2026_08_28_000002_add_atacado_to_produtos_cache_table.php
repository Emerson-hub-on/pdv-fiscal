<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlite_local')->table('produtos_cache', function (Blueprint $table) {
            $table->decimal('preco_atacado', 10, 2)->nullable();
            $table->decimal('quantidade_minima_atacado', 10, 3)->nullable();
            $table->boolean('atacado_tem_prazo')->default(false);
            $table->date('atacado_data_inicio')->nullable();
            $table->date('atacado_data_fim')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->table('produtos_cache', function (Blueprint $table) {
            $table->dropColumn([
                'preco_atacado', 'quantidade_minima_atacado',
                'atacado_tem_prazo', 'atacado_data_inicio', 'atacado_data_fim',
            ]);
        });
    }
};
