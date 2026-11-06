<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Itens de venda do caixa com quantidade fracionada: produto de balança vendido por peso (kg, 3 casas). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venda_itens', function (Blueprint $t) {
            $t->decimal('quantidade', 12, 3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('venda_itens', function (Blueprint $t) {
            $t->integer('quantidade')->change();
        });
    }
};
