<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();

            $table->enum('tipo_pessoa', ['fisica', 'juridica']);
            $table->string('nome'); // nome completo (PF) ou razao social (PJ)
            $table->string('nome_fantasia')->nullable(); // so PJ
            $table->string('cpf_cnpj', 14)->unique(); // digitos apenas, 11 (CPF) ou 14 (CNPJ)

            // indIEDest do XML: 1=Contribuinte ICMS, 2=Contribuinte isento, 9=Nao Contribuinte.
            // PF normalmente e sempre 'nao_contribuinte'. PJ pode ser qualquer um dos 3 -
            // orgaos publicos/hospitais tipicamente sao 'nao_contribuinte' mesmo sendo PJ.
            $table->enum('indicador_ie', ['contribuinte', 'isento', 'nao_contribuinte'])
                ->default('nao_contribuinte');
            $table->string('ie', 20)->nullable(); // obrigatorio apenas quando indicador_ie = contribuinte

            $table->string('email')->nullable();
            $table->string('telefone', 20)->nullable();

            // Endereco completo - obrigatorio pra NF-e modelo 55 (futuro), opcional na
            // pratica pra NFC-e modelo 65 (que aceita emissao so com CPF/CNPJ)
            $table->string('cep', 8)->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento', 100)->nullable();
            $table->string('bairro', 100)->nullable();
            $table->string('municipio', 100)->nullable();
            $table->string('cod_municipio', 7)->nullable(); // codigo IBGE, exigido no XML
            $table->string('uf', 2)->nullable();

            $table->boolean('ativo')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
