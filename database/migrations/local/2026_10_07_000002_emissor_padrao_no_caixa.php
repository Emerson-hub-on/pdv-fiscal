<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('sqlite_local')->table('pdvs_cache')->update(['emissao_local' => true]);
    }

    public function down(): void
    {
        DB::connection('sqlite_local')->table('pdvs_cache')->update(['emissao_local' => false]);
    }
};