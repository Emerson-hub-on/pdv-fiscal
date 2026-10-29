<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CST, base e alíquotas de PIS/COFINS por item de saída, como foram para o XML.
     * O valor (valor_pis/valor_cofins) já existia; faltava o resto para a EFD-Contribuições.
     * O mesmo CST vale para PIS e COFINS (o sistema usa uma classificação só para os dois).
     */
    public function up(): void
    {
        foreach (['nota_fiscal_itens', 'venda_itens'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->string('cst_pis_cofins', 2)->nullable()->after('valor_cofins');
                $table->decimal('bc_pis_cofins', 15, 2)->default(0)->after('cst_pis_cofins');
                $table->decimal('aliquota_pis', 8, 4)->default(0)->after('bc_pis_cofins');
                $table->decimal('aliquota_cofins', 8, 4)->default(0)->after('aliquota_pis');
            });
        }
    }

    public function down(): void
    {
        foreach (['nota_fiscal_itens', 'venda_itens'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn(['cst_pis_cofins', 'bc_pis_cofins', 'aliquota_pis', 'aliquota_cofins']);
            });
        }
    }
};
