<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classificacoes_tributarias', function (Blueprint $table) {
            $table->decimal('percentual_reducao_ibs', 5, 2)->nullable()->change();
            $table->decimal('percentual_reducao_cbs', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('classificacoes_tributarias', function (Blueprint $table) {
            $table->decimal('percentual_reducao_ibs', 6, 4)->nullable()->change();
            $table->decimal('percentual_reducao_cbs', 6, 4)->nullable()->change();
        });
    }
};