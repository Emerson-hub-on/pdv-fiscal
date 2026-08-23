<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            // Nullable: nem todo produto tem CEST (só os sujeitos a ST/Antecipação)
            $table->foreignId('cest_id')->nullable()->after('ncm_id')->constrained('cests');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cest_id');
        });
    }
};