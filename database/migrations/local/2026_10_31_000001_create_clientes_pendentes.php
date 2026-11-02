<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite do caixa: clientes do cadastro rápido feito no caixa, guardados aqui até a
 * sincronização criar o cadastro no servidor. A venda aponta para eles por cliente_local_id
 * e recebe o cliente_id do servidor quando o cliente sobe.
 */
return new class extends Migration
{
    protected $connection = 'sqlite_local';

    public function up(): void
    {
        Schema::connection('sqlite_local')->create('clientes_pendentes', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_pessoa');
            $table->string('nome');
            $table->string('cpf_cnpj', 14)->unique();
            $table->unsignedBigInteger('operador_id')->nullable();
            $table->unsignedBigInteger('id_central')->nullable(); // preenchido quando o cliente sobe
            $table->text('sync_erro')->nullable();
            $table->timestamps();
        });

        Schema::connection('sqlite_local')->table('vendas_pendentes', function (Blueprint $table) {
            $table->unsignedBigInteger('cliente_local_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->table('vendas_pendentes', function (Blueprint $table) {
            $table->dropColumn('cliente_local_id');
        });

        Schema::connection('sqlite_local')->dropIfExists('clientes_pendentes');
    }
};
