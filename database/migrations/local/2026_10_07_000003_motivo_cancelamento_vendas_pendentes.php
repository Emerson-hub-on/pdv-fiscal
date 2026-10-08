<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlite_local')->table('vendas_pendentes', function (Blueprint $t) {
            $t->text('motivo_cancelamento')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->table('vendas_pendentes', function (Blueprint $t) {
            $t->dropColumn('motivo_cancelamento');
        });
    }
};