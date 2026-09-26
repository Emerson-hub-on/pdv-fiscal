<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nota_fiscal_itens', function (Blueprint $table) {
            $table->string('descricao')->nullable()->after('produto_id');
        });
    }

    public function down(): void
    {
        Schema::table('nota_fiscal_itens', function (Blueprint $table) {
            $table->dropColumn('descricao');
        });
    }
};