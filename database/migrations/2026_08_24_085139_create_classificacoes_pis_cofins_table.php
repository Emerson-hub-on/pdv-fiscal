<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classificacoes_pis_cofins', function (Blueprint $table) {
            $table->id();
            // CST PIS/COFINS de saída: 01-09, 49 (Tabelas 4.3.3/4.3.4 SPED, IN RFB 1.009/2010).
            // NAO e unique: o mesmo CST pode ter mais de um registro (ex: alíquota básica
            // no regime não-cumulativo vs cumulativo) - quem diferencia é a descrição/alíquota.
            $table->string('codigo', 2)->index();
            $table->string('descricao');
            // Alíquotas ficam nullable de propósito - dependem do regime de apuração
            // da empresa (cumulativo/não-cumulativo) e devem ser confirmadas pelo contador.
            $table->decimal('aliquota_pis', 6, 4)->nullable();
            $table->decimal('aliquota_cofins', 6, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classificacoes_pis_cofins');
    }
};