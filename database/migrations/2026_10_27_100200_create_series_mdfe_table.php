<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series_mdfe', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('serie')->default(1);
            $table->unsignedInteger('numero_atual')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('serie');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series_mdfe');
    }
};
