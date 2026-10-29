<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite do caixa: registro do que aconteceu no caixa e de quem liberou
 * (descontos, cancelamentos de NFC-e e autorizações do supervisor).
 * É a base das seções de cancelamentos/descontos do relatório de fechamento.
 */
return new class extends Migration
{
    protected $connection = 'sqlite_local';

    public function up(): void
    {
        Schema::connection('sqlite_local')->create('ocorrencias_caixa', function (Blueprint $table) {
            $table->id();
            $table->string('caixa_uuid')->nullable()->index();
            $table->string('venda_uuid')->nullable()->index();
            // desconto_item | desconto_global | cancelamento_nfce | autorizacao
            $table->string('tipo', 30);
            $table->string('acao', 50)->nullable();      // ação do config permissoes.caixa.acoes (nas autorizações)
            $table->string('descricao')->nullable();     // item com desconto, justificativa do cancelamento...
            $table->decimal('valor', 12, 2)->default(0);
            $table->unsignedBigInteger('operador_id')->nullable();
            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->string('supervisor_nome')->nullable();
            $table->boolean('liberado_operador')->default(false); // operador com a ação liberada (sem supervisor)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->dropIfExists('ocorrencias_caixa');
    }
};
