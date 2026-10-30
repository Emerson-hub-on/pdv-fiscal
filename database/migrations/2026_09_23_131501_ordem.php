<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfop_saida', function (Blueprint $table) {
            $table->unsignedInteger('ordem')->nullable()->after('finalidade_padrao');
        });
    }

    public function down(): void
    {
        Schema::table('cfop_saida', function (Blueprint $table) {
            $table->dropColumn('ordem');
        });
    }
};