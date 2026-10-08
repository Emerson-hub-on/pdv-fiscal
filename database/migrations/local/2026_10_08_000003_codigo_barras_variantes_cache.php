<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Migration do SQLite local (caixa). Coloque na mesma pasta da
// 2026_10_08_000001_inutilizacoes_local e rode do mesmo jeito.
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlite_local');

        $schema->table('produto_variantes_cache', function (Blueprint $table) use ($schema) {
            if (! $schema->hasColumn('produto_variantes_cache', 'sku')) {
                $table->string('sku')->nullable();
            }
            if (! $schema->hasColumn('produto_variantes_cache', 'codigo_barras')) {
                $table->string('codigo_barras', 50)->nullable()->index();
            }
            if (! $schema->hasColumn('produto_variantes_cache', 'codigo_barras_valido')) {
                // true = EAN real (vai no XML); false = código interno gerado
                $table->boolean('codigo_barras_valido')->default(false);
            }
        });

        // Força o próximo sync a repuxar o catálogo inteiro: as variantes que já
        // estão no cache foram gravadas sem esses campos.
        DB::connection('sqlite_local')->table('sync_meta')
            ->where('chave', 'ultima_sincronizacao_produtos')
            ->delete();
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->table('produto_variantes_cache', function (Blueprint $table) {
            $table->dropColumn(['sku', 'codigo_barras', 'codigo_barras_valido']);
        });
    }
};
