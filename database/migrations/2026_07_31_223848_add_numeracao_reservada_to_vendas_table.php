<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->unsignedBigInteger('numero_nfce')->nullable()->after('protocolo_nfe');
            $table->unsignedInteger('serie_nfce')->nullable()->after('numero_nfce');
        });

        // SQLite/MySQL: ajustar enum de status pra incluir "contingencia"
        DB::statement("ALTER TABLE vendas MODIFY status ENUM('pendente', 'emitida', 'cancelada', 'contingencia') DEFAULT 'pendente'");
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn(['numero_nfce', 'serie_nfce']);
        });

        DB::statement("ALTER TABLE vendas MODIFY status ENUM('pendente', 'emitida', 'cancelada') DEFAULT 'pendente'");
    }
};
