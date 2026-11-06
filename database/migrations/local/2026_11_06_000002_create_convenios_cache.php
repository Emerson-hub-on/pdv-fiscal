<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite do caixa: convênio (fiado).
 * - convenios_cache: clientes com limite concedido, o limite e quanto já está usado
 *   (servidor + vendas deste caixa que ainda não subiram). A venda soma no "usado" na hora (offline).
 * - convenios_abertos_cache: as dívidas de convênio em aberto no servidor, para a consulta do caixa.
 */
return new class extends Migration
{
    protected $connection = 'sqlite_local';

    public function up(): void
    {
        Schema::connection('sqlite_local')->create('convenios_cache', function (Blueprint $table) {
            $table->unsignedBigInteger('cliente_id')->primary();
            $table->string('cliente_nome')->nullable();
            $table->string('cpf_cnpj', 14)->nullable();
            $table->decimal('limite', 12, 2)->default(0);
            $table->decimal('usado', 12, 2)->default(0);
            $table->unsignedSmallInteger('dias')->default(30);
            $table->timestamp('updated_at')->nullable();
        });

        Schema::connection('sqlite_local')->create('convenios_abertos_cache', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // id da conta a receber no servidor
            $table->unsignedBigInteger('cliente_id')->nullable()->index();
            $table->string('cliente_nome')->nullable();
            $table->string('venda_uuid', 36)->nullable()->index();
            $table->unsignedInteger('numero_nfce')->nullable()->index();
            $table->unsignedSmallInteger('serie_nfce')->nullable();
            $table->date('data_venda')->nullable()->index();
            $table->date('vencimento')->nullable();
            $table->decimal('valor', 12, 2)->default(0);
            $table->decimal('saldo', 12, 2)->default(0);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->dropIfExists('convenios_abertos_cache');
        Schema::connection('sqlite_local')->dropIfExists('convenios_cache');
    }
};
