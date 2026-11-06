<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite do caixa: créditos em loja (vale-troca) com saldo, trazidos do servidor no Atualizar Caixa.
 * A venda do caixa abate o saldo aqui na hora (funciona offline); o servidor confirma quando a venda sobe.
 */
return new class extends Migration
{
    protected $connection = 'sqlite_local';

    public function up(): void
    {
        Schema::connection('sqlite_local')->create('creditos_loja_cache', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // id do servidor
            $table->string('codigo', 12)->unique();
            $table->unsignedBigInteger('cliente_id')->nullable()->index();
            $table->string('cliente_nome')->nullable();
            $table->decimal('saldo', 12, 2);
            $table->string('status', 12)->default('ativo');
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->dropIfExists('creditos_loja_cache');
    }
};
