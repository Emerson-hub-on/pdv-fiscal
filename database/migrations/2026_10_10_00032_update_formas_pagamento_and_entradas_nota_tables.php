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
        // 1. Adiciona as colunas na tabela 'formas_pagamento'
        Schema::table('formas_pagamento', function (Blueprint $t) {
            $t->boolean('uso_saida')->default(true);
            $t->boolean('uso_entrada')->default(true);
        });

        // 2. Adiciona a chave estrangeira na tabela 'entradas_nota'
        Schema::table('entradas_nota', function (Blueprint $t) {
            $t->foreignId('forma_pagamento_id')
              ->nullable()
              ->constrained('formas_pagamento')
              ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Remove a chave estrangeira e a coluna na tabela 'entradas_nota' (ordem inversa)
        Schema::table('entradas_nota', function (Blueprint $t) {
            $t->dropForeign(['forma_pagamento_id']);
            $t->dropColumn('forma_pagamento_id');
        });

        // 2. Remove as colunas da tabela 'formas_pagamento'
        Schema::table('formas_pagamento', function (Blueprint $t) {
            $t->dropColumn(['uso_saida', 'uso_entrada']);
        });
    }
};
