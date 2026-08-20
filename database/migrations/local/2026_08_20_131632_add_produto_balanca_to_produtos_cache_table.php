<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos_cache', function (Blueprint $table) {
            $table->boolean('produto_balanca')->default(false)->after('tem_variacao');
        });
    }

    public function down(): void
    {
        Schema::table('produtos_cache', function (Blueprint $table) {
            $table->dropColumn('produto_balanca');
        });
    }
};
