<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('produtos', 'referencia')) {
            return;
        }

        Schema::table('produtos', function (Blueprint $table) {
            $table->string('referencia', 60)->nullable()->after('codigo_barras')->index();
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropIndex(['referencia']);
            $table->dropColumn('referencia');
        });
    }
};