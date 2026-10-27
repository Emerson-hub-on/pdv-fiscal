<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
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
        // Nada a desfazer: a série pode já ter numeração em uso.
    }
};
