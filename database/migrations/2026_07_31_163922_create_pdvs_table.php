<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdvs', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->unsignedInteger('serie_nfce');
            $table->unsignedBigInteger('numero_atual_nfce')->default(0);
            $table->string('csc')->nullable();
            $table->string('csc_id')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdvs');
    }
};
