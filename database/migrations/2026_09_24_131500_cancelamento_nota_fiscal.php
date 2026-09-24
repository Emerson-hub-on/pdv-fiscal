<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->string('protocolo_cancelamento')->nullable()->after('protocolo');
            $table->timestamp('cancelado_em')->nullable()->after('emitida_em');
        });
    }

    public function down(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->dropColumn(['protocolo_cancelamento', 'cancelado_em']);
        });
    }
};