<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard: se uma execução anterior falhou depois de criar a coluna,
        // não tenta criar de novo.
        if (! Schema::hasColumn('produto_variantes', 'codigo_barras_valido')) {
            Schema::table('produto_variantes', function (Blueprint $table) {
                // true = EAN/GTIN real digitado (vai no XML); false = código interno gerado
                $table->boolean('codigo_barras_valido')->default(false)->after('codigo_barras');
            });
        }

        // Backfill linha a linha (pode ser repetido sem efeito colateral):
        // - sem código: ganha o código interno gerado (valido = false)
        // - com código que não é o interno gerado: é EAN real (valido = true)
        DB::table('produto_variantes')->orderBy('id')->each(function ($variante) {
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

    // Mesma lógica de ProdutoVariante::codigoInternoPara() — copiada aqui de
    // propósito: migration não deve depender de código do model que pode mudar.
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