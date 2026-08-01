<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caixas', function (Blueprint $table) {
            $table->foreignId('pdv_id')->nullable()->after('operador_id')->constrained('pdvs');
        });
    }

    public function down(): void
    {
        Schema::table('caixas', function (Blueprint $table) {
            $table->dropForeign(['pdv_id']);
            $table->dropColumn('pdv_id');
        });
    }
};
