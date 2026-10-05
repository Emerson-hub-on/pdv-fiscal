<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caixas', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        // Caixas que já existem ganham um uuid
        foreach (DB::table('caixas')->whereNull('uuid')->pluck('id') as $id) {
            DB::table('caixas')->where('id', $id)->update(['uuid' => (string) Str::uuid()]);
        }
    }

    public function down(): void
    {
        Schema::table('caixas', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};