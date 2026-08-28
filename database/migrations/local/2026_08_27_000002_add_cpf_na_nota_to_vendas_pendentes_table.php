<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlite_local')->table('vendas_pendentes', function (Blueprint $table) {
            $table->string('cpf_na_nota', 11)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->table('vendas_pendentes', function (Blueprint $table) {
            $table->dropColumn('cpf_na_nota');
        });
    }
};
