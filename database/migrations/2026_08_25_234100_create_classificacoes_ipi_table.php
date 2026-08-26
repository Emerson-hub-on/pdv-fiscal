<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classificacoes_ipi', function (Blueprint $table) {
            $table->id();
            // CST IPI de saida: 50,51,52,53,54,55,99 (Tabela SPED, mesma familia
            // do CST PIS/COFINS - IN RFB 1.009/2010, estavel desde entao).
            $table->string('codigo', 2)->index();
            $table->string('descricao');
            // Codigo de Enquadramento Legal - "999" (tributacao normal/sem
            // enquadramento especifico) cobre a esmagadora maioria dos casos de
            // comercio nao-industrial. So muda se o cliente for industrial/
            // equiparado e tiver um enquadramento especifico da TIPI.
            $table->string('cenq', 3)->default('999');
            // Aliquota so se aplica de fato ao CST 50 (saida tributada) - fica
            // nullable pros demais CSTs, que nao tem aliquota.
            $table->decimal('aliquota', 6, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classificacoes_ipi');
    }
};