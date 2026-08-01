<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas_pendentes', function (Blueprint $table) {
            $table->json('pagamentos')->nullable()->after('forma_pagamento');
        });
    }

    public function down(): void
    {
        Schema::table('vendas_pendentes', function (Blueprint $table) {
            $table->dropColumn('pagamentos');
        });
    }
};
