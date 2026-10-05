<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlite_local')->dropIfExists('caixas_cache'); // substituída pela caixas_local

        Schema::connection('sqlite_local')->create('caixas_local', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('id_central')->nullable()->unique(); // preenchido quando o servidor conhece o caixa
            $table->unsignedBigInteger('operador_id');                      // users.id
            $table->unsignedBigInteger('pdv_id');
            $table->dateTime('data_abertura');
            $table->decimal('valor_abertura', 10, 2)->default(0);
            $table->dateTime('data_fechamento')->nullable();
            $table->decimal('valor_fechamento_informado', 10, 2)->nullable();
            $table->decimal('valor_fechamento_esperado', 10, 2)->nullable();
            $table->string('status')->default('aberto');
            $table->text('observacao')->nullable();
            $table->boolean('sync_pendente')->default(true);
            $table->text('sync_erro')->nullable();
            $table->dateTime('sincronizado_em')->nullable();
            $table->timestamps();

            $table->index(['operador_id', 'status']);
        });

        Schema::connection('sqlite_local')->table('vendas_pendentes', function (Blueprint $table) {
            $table->string('caixa_uuid')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->table('vendas_pendentes', function (Blueprint $table) {
            $table->dropColumn('caixa_uuid');
        });

        Schema::connection('sqlite_local')->dropIfExists('caixas_local');
    }
};