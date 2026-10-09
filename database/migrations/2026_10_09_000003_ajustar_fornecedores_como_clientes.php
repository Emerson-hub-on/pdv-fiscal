<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alinha a tabela `fornecedores` (criada na etapa da entrada de nota) com a
 * estrutura de `clientes`, para o mesmo cadastro completo e para que o
 * fornecedor possa receber nota fiscal no faturamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fornecedores', function (Blueprint $table) {
            $table->renameColumn('cnpj_cpf', 'cpf_cnpj');
            $table->renameColumn('razao_social', 'nome');
        });

        Schema::table('fornecedores', function (Blueprint $table) {
            $table->string('tipo_pessoa', 10)->default('juridica')->after('id');
            $table->string('indicador_ie', 20)->default('nao_contribuinte')->after('nome_fantasia');

            // Tamanhos iguais aos validados no cadastro de clientes
            $table->string('logradouro', 255)->nullable()->change();
            $table->string('complemento', 100)->nullable()->change();
            $table->string('bairro', 100)->nullable()->change();
            $table->string('municipio', 100)->nullable()->change();
            $table->string('email', 255)->nullable()->change();
        });

        // Registros que já existiam: define o tipo pelo tamanho do documento
        // e marca como contribuinte quem já tinha IE informada.
        DB::table('fornecedores')->whereRaw('CHAR_LENGTH(cpf_cnpj) = 11')->update(['tipo_pessoa' => 'fisica']);
        DB::table('fornecedores')->whereNotNull('ie')->where('ie', '!=', '')->update(['indicador_ie' => 'contribuinte']);
    }

    public function down(): void
    {
        Schema::table('fornecedores', function (Blueprint $table) {
            $table->dropColumn(['tipo_pessoa', 'indicador_ie']);
        });

        Schema::table('fornecedores', function (Blueprint $table) {
            $table->renameColumn('cpf_cnpj', 'cnpj_cpf');
            $table->renameColumn('nome', 'razao_social');
        });
    }
};
