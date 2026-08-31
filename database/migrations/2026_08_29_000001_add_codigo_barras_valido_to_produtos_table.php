<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Antes de criar o unique, preenche os produtos existentes que estao
        // com codigo_barras vazio - senao o unique index quebra na criacao
        // (varios NULLs nao colidem, mas strings vazias colidiriam entre si).
        $produtosSemCodigo = DB::table('produtos')
            ->where(function ($q) {
                $q->whereNull('codigo_barras')->orWhere('codigo_barras', '');
            })
            ->get(['id', 'codigo_interno']);

        foreach ($produtosSemCodigo as $produto) {
            $fallback = str_pad(preg_replace('/\D/', '', (string) $produto->codigo_interno), 13, '0', STR_PAD_LEFT);
            DB::table('produtos')->where('id', $produto->id)->update([
                'codigo_barras' => $fallback,
            ]);
        }

        Schema::table('produtos', function (Blueprint $table) {
            // true = GTIN real (digitado pelo usuario ou lido por leitora).
            // false = gerado automaticamente a partir do codigo interno -
            // nunca deve ir pro XML fiscal como GTIN.
            $table->boolean('codigo_barras_valido')->default(true)->after('codigo_barras');
        });

        // Marca os que acabaram de ganhar o fallback como invalidos
        DB::table('produtos')
            ->whereIn('id', $produtosSemCodigo->pluck('id'))
            ->update(['codigo_barras_valido' => false]);

        Schema::table('produtos', function (Blueprint $table) {
            $table->unique('codigo_barras');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropUnique(['codigo_barras']);
            $table->dropColumn('codigo_barras_valido');
        });
    }
};
