<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordens_servico', function (Blueprint $t) {
            // cliente_id vazio = cliente avulso (sem cadastro): os dados ficam na própria OS
            $t->unsignedBigInteger('cliente_id')->nullable()->change();

            $t->string('cliente_nome', 150)->nullable()->after('cliente_id');
            $t->string('cliente_telefone', 30)->nullable()->after('cliente_nome');
            $t->string('cliente_documento', 14)->nullable()->after('cliente_telefone'); // só dígitos (CPF ou CNPJ)
        });
    }

    public function down(): void
    {
        Schema::table('ordens_servico', function (Blueprint $t) {
            $t->dropColumn(['cliente_nome', 'cliente_telefone', 'cliente_documento']);
            // cliente_id volta a ser obrigatório só se não houver OS avulsa no banco
        });
    }
};
