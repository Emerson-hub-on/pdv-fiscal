<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Garante que a coluna 'codigo_barras' existe na tabela
        if (! Schema::hasColumn('produto_variantes', 'codigo_barras')) {
            Schema::table('produto_variantes', function (Blueprint $table) {
                $table->string('codigo_barras')->nullable();
            });
        }

        // 2. Garante que a coluna 'codigo_barras_valido' existe na tabela
        if (! Schema::hasColumn('produto_variantes', 'codigo_barras_valido')) {
            Schema::table('produto_variantes', function (Blueprint $table) {
                $table->boolean('codigo_barras_valido')->default(false);
            });
        }

        // 3. Backfill linha a linha com segurança
        DB::table('produto_variantes')
            ->select('id', 'codigo_barras')
            ->orderBy('id')
            ->each(function ($variante) {
                $interno = $this->codigoInternoPara((int) $variante->id);

                if ($variante->codigo_barras === null) {
                    DB::table('produto_variantes')->where('id', $variante->id)->update([
                        'codigo_barras' => $interno,
                        'codigo_barras_valido' => false,
                    ]);
                } elseif ($variante->codigo_barras !== $interno) {
                    DB::table('produto_variantes')->where('id', $variante->id)->update([
                        'codigo_barras_valido' => true,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('produto_variantes', function (Blueprint $table) {
            $table->dropColumn('codigo_barras_valido');
        });
    }

    private function codigoInternoPara(int $id): string
    {
        $base = '99' . str_pad((string) $id, 10, '0', STR_PAD_LEFT);

        $soma = 0;
        foreach (array_reverse(str_split($base)) as $posicao => $digito) {
            $soma += ((int) $digito) * ($posicao % 2 === 0 ? 3 : 1);
        }

        return $base . ((10 - ($soma % 10)) % 10);
    }
};