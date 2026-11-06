<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** SQLite do caixa: formas de pagamento cadastradas no painel, trazidas no Atualizar Caixa (vale offline). */
return new class extends Migration
{
    protected $connection = 'sqlite_local';

    public function up(): void
    {
        Schema::connection('sqlite_local')->create('formas_pagamento_cache', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('codigo', 20)->unique();
            $table->string('descricao', 60);
            $table->string('meio_pagamento', 2);
            $table->string('tipo', 20)->default('outro');
            $table->boolean('permite_troco')->default(false);
            $table->boolean('ativo')->default(true);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->dropIfExists('formas_pagamento_cache');
    }
};
