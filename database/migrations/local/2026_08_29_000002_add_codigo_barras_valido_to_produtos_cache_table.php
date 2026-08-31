<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlite_local')->table('produtos_cache', function (Blueprint $table) {
            $table->boolean('codigo_barras_valido')->default(true);
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->table('produtos_cache', function (Blueprint $table) {
            $table->dropColumn('codigo_barras_valido');
        });
    }
};
