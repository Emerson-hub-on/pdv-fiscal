<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            // Opcional de proposito: clientes que nao sao estabelecimento
            // industrial/equiparado simplesmente nao preenchem, e o emissor usa
            // um CST fixo padrao (53 - saida nao tributada) automaticamente.
            $table->foreignId('ipi_id')->nullable()->after('pis_cofins_id')
                ->constrained('classificacoes_ipi');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ipi_id');
        });
    }
};