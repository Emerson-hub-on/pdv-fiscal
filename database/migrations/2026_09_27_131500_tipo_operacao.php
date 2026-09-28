<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfop_saida', function (Blueprint $table) {
            $table->enum('tipo_operacao', ['entrada', 'saida'])->default('saida')->after('descricao');
        });

        DB::table('cfop_saida')
            ->whereRaw("LEFT(codigo, 1) IN ('1', '2', '3')")
            ->update(['tipo_operacao' => 'entrada']);
    }

    public function down(): void
    {
        Schema::table('cfop_saida', function (Blueprint $table) {
            $table->dropColumn('tipo_operacao');
        });
    }
};