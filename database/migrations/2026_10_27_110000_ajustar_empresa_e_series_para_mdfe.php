<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RNTRC do emitente (8 dígitos, ANTT) — preenchido por padrão no MDF-e
        Schema::table('empresa', function (Blueprint $table) {
            $table->string('rntrc', 8)->nullable()->after('crt');
        });

        // Série padrão do MDF-e (modelo 58), para a primeira emissão não depender de cadastro manual
        if (DB::table('series_mdfe')->count() === 0) {
            DB::table('series_mdfe')->insert([
                'serie'        => 1,
                'numero_atual' => 0,
                'ativo'        => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn('rntrc');
        });
    }
};
