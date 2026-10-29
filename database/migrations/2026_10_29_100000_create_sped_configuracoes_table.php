<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registro único (como a tabela "empresa"): dados que o arquivo SPED pede e que não existem nos outros cadastros
        Schema::create('sped_configuracoes', function (Blueprint $table) {
            $table->id();

            // 1 = comércio/outros, 0 = industrial ou equiparado a industrial (IND_ATIV do registro 0000)
            $table->unsignedTinyInteger('ind_ativ')->default(1);

            // Contato da empresa (registro 0005)
            $table->string('empresa_fone', 11)->nullable();
            $table->string('empresa_email', 100)->nullable();

            // Contabilista (registro 0100)
            $table->string('contador_nome', 100)->nullable();
            $table->string('contador_cpf', 11)->nullable();
            $table->string('contador_crc', 15)->nullable();
            $table->string('contador_cnpj', 14)->nullable(); // escritório, se houver
            $table->string('contador_cep', 8)->nullable();
            $table->string('contador_endereco', 60)->nullable();
            $table->string('contador_numero', 10)->nullable();
            $table->string('contador_complemento', 60)->nullable();
            $table->string('contador_bairro', 60)->nullable();
            $table->string('contador_municipio', 100)->nullable();
            $table->string('contador_cod_municipio', 7)->nullable();
            $table->string('contador_uf', 2)->nullable();
            $table->string('contador_fone', 11)->nullable();
            $table->string('contador_email', 100)->nullable();

            $table->timestamps();
        });

        // Tipo do item (campo TIPO_ITEM do 0200): 00 = mercadoria para revenda é o padrão do comércio
        Schema::table('produtos', function (Blueprint $table) {
            $table->string('tipo_item', 2)->default('00')->after('unidade_tributavel');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn('tipo_item');
        });

        Schema::dropIfExists('sped_configuracoes');
    }
};
