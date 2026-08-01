<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn(['serie_nfce', 'numero_atual_nfce', 'csc', 'csc_id']);
        });
    }

    public function down(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->unsignedInteger('serie_nfce')->default(1);
            $table->unsignedBigInteger('numero_atual_nfce')->default(0);
            $table->string('csc')->nullable();
            $table->string('csc_id')->nullable();
        });
    }
};
