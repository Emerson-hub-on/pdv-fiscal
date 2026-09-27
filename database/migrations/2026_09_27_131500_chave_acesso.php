<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nota_fiscal_itens', function (Blueprint $table) {
            $table->string('ref_chave_acesso', 44)->nullable()->after('descricao');
            $table->unsignedInteger('ref_nitem')->nullable()->after('ref_chave_acesso');
        });
    }

    public function down(): void
    {
        Schema::table('nota_fiscal_itens', function (Blueprint $table) {
            $table->dropColumn(['ref_chave_acesso', 'ref_nitem']);
        });
    }
};