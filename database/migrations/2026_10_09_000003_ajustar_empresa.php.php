

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
        Schema::table('empresa', function (Blueprint $table) {
            $table->unsignedBigInteger('entrada_tributacao_padrao_id')->nullable();
            $table->unsignedBigInteger('entrada_tributacao_st_padrao_id')->nullable();
            $table->unsignedBigInteger('entrada_pis_cofins_padrao_id')->nullable();
            $table->decimal('entrada_margem_padrao', 6, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn([
                'entrada_tributacao_padrao_id',
                'entrada_tributacao_st_padrao_id',
                'entrada_pis_cofins_padrao_id',
                'entrada_margem_padrao',
            ]);
        });
    }
};