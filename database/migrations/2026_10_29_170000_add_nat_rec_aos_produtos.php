<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Natureza da receita (NAT_REC) do PIS/COFINS, tabelas 4.3.10 a 4.3.16 da EFD-Contribuições.
     * Obrigatória no M410/M810 para produtos vendidos com CST 04 a 09 (monofásico, alíquota zero, isento...).
     * Depende do NCM do produto, por isso fica no cadastro do produto.
     */
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->string('nat_rec_pis_cofins', 3)->nullable()->after('tipo_item');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn('nat_rec_pis_cofins');
        });
    }
};
