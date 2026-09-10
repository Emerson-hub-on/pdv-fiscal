<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn(['serie_nfe', 'numero_atual_nfe']);
        });
    }

    public function down(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->unsignedInteger('serie_nfe')->default(1);
            $table->unsignedBigInteger('numero_atual_nfe')->default(0);
        });
    }
};