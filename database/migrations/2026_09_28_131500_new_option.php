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
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->string('motivo_ajuste', 2)->nullable()->after('finalidade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->dropColumn('motivo_ajuste');
        });
    }
};
