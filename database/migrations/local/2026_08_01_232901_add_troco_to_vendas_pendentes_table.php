<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas_pendentes', function (Blueprint $table) {
            $table->decimal('troco', 12, 2)->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('vendas_pendentes', function (Blueprint $table) {
            $table->dropColumn('troco');
        });
    }
};
