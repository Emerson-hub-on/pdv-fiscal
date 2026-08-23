<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cests', function (Blueprint $table) {
            $table->text('descricao')->change();
            $table->text('segmento_descricao')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cests', function (Blueprint $table) {
            $table->string('descricao')->change();
            $table->string('segmento_descricao')->nullable()->change();
        });
    }
};