<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite do caixa: destinatário (CPF, nome e endereço de entrega) da NFC-e de entrega em domicílio (iFood, indPres 4).
 * JSON com cpf, nome, xLgr, nro, xCpl, xBairro, cMun, xMun, UF, CEP.
 */
return new class extends Migration
{
    protected $connection = 'sqlite_local';

    public function up(): void
    {
        Schema::connection('sqlite_local')->table('vendas_pendentes', function (Blueprint $table) {
            $table->text('destinatario')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->table('vendas_pendentes', function (Blueprint $table) {
            $table->dropColumn('destinatario');
        });
    }
};
