<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Migration do SQLite local (caixa): mesma pasta da 2026_10_08_000003_codigo_barras_variantes_cache.
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlite_local');

        if ($schema->hasColumn('produto_variantes_cache', 'sku')) {
            $schema->table('produto_variantes_cache', function (Blueprint $table) {
                $table->dropColumn('sku');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlite_local');

        if (! $schema->hasColumn('produto_variantes_cache', 'sku')) {
            $schema->table('produto_variantes_cache', function (Blueprint $table) {
                $table->string('sku')->nullable();
            });
        }
    }
};
