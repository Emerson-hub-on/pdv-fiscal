<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->unsignedTinyInteger('tp_emis')->default(1)->after('numero_nfce');
            $table->timestamp('dh_cont')->nullable()->after('tp_emis');
            $table->text('x_just')->nullable()->after('dh_cont');
            $table->longText('xml_contingencia')->nullable()->after('x_just');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn(['tp_emis', 'dh_cont', 'x_just', 'xml_contingencia']);
        });
    }
};
