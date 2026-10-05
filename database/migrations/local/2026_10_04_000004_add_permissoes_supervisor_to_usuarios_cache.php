<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlite_local')->table('usuarios_cache', function (Blueprint $table) {
            $table->text('permissoes_supervisor')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->table('usuarios_cache', function (Blueprint $table) {
            $table->dropColumn('permissoes_supervisor');
        });
    }
};