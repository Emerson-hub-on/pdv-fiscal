<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('sqlite_local')->table('pdvs_cache', function (Blueprint $t) {
            $t->string('maquina', 60)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlite_local')->table('pdvs_cache', function (Blueprint $t) {
            $t->dropColumn('maquina');
        });
    }
};
