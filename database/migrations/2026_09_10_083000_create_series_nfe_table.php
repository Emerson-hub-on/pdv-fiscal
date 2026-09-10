<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series_nfe', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('serie');
            $table->unsignedBigInteger('numero_atual')->default(0);
            $table->string('descricao')->nullable();
            $table->boolean('ativa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series_nfe');
    }
};