<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $sqlite = Schema::connection('sqlite_local');

        // Dados do emitente + certificado (cifrado com a APP_KEY)
        $sqlite->create('empresa_cache', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('cnpj');
            $table->string('razao_social');
            $table->string('nome_fantasia')->nullable();
            $table->string('ie')->nullable();
            $table->string('im')->nullable();
            $table->unsignedTinyInteger('crt');
            $table->string('logradouro');
            $table->string('numero');
            $table->string('complemento')->nullable();
            $table->string('bairro');
            $table->string('cep');
            $table->string('municipio');
            $table->string('cod_municipio');
            $table->string('uf', 2);
            $table->unsignedTinyInteger('ambiente');      // 1 produção, 2 homologação
            $table->text('certificado')->nullable();       // .pfx em base64, cifrado
            $table->text('certificado_senha')->nullable(); // cifrada
            $table->date('certificado_validade')->nullable();
            $table->timestamps();
        });

        // CSC por PDV (o CSC fica cifrado)
        $sqlite->table('pdvs_cache', function (Blueprint $table) {
            $table->text('csc')->nullable();
            $table->string('csc_id')->nullable();
        });

        // Tributação do produto, necessária para montar o XML sem o servidor
        $sqlite->table('produtos_cache', function (Blueprint $table) {
            $table->string('cst_icms')->nullable();
            $table->decimal('aliquota_icms', 8, 4)->nullable();
            $table->string('pis_cofins_cst')->nullable();
            $table->decimal('aliquota_pis', 8, 4)->nullable();
            $table->decimal('aliquota_cofins', 8, 4)->nullable();
            $table->string('class_trib_cst')->nullable();             // CST do IBS/CBS
            $table->decimal('percentual_reducao_ibs', 8, 4)->nullable();
            $table->decimal('percentual_reducao_cbs', 8, 4)->nullable();
        });

        // O destinatário pode levar e-mail no XML
        $sqlite->table('clientes_cache', function (Blueprint $table) {
            $table->string('email')->nullable();
        });

        // Contador de numeração da NFC-e, de propriedade do caixa (usado na etapa 3)
        $sqlite->create('numeracao_nfce', function (Blueprint $table) {
            $table->unsignedBigInteger('pdv_id')->primary();
            $table->string('serie');
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->timestamps();
        });

        // Situação fiscal da venda (o "status" continua sendo o da sincronização)
        $sqlite->table('vendas_pendentes', function (Blueprint $table) {
            $table->string('status_fiscal')->default('pendente'); // pendente | emitida | contingencia
            $table->unsignedBigInteger('numero_nfce')->nullable();
            $table->string('serie_nfce')->nullable();
            $table->string('chave_nfe')->nullable();
            $table->string('protocolo_nfe')->nullable();
            $table->unsignedTinyInteger('tp_emis')->nullable();
            $table->string('dh_cont')->nullable();
            $table->string('x_just')->nullable();
            $table->text('xml_contingencia')->nullable();
            $table->string('ultimo_arquivo_xml')->nullable();
            $table->text('motivo_rejeicao')->nullable();
            $table->dateTime('emitida_em')->nullable();
        });

        // Produtos e clientes já sincronizados não têm as colunas novas: força ressincronização completa
        DB::connection('sqlite_local')->table('sync_meta')
            ->whereIn('chave', ['ultima_sincronizacao_produtos', 'ultima_sincronizacao_clientes'])
            ->delete();
    }

    public function down(): void
    {
        $sqlite = Schema::connection('sqlite_local');

        $sqlite->table('vendas_pendentes', function (Blueprint $table) {
            $table->dropColumn([
                'status_fiscal', 'numero_nfce', 'serie_nfce', 'chave_nfe', 'protocolo_nfe', 'tp_emis',
                'dh_cont', 'x_just', 'xml_contingencia', 'ultimo_arquivo_xml', 'motivo_rejeicao', 'emitida_em',
            ]);
        });

        $sqlite->dropIfExists('numeracao_nfce');

        $sqlite->table('clientes_cache', fn (Blueprint $table) => $table->dropColumn('email'));

        $sqlite->table('produtos_cache', function (Blueprint $table) {
            $table->dropColumn([
                'cst_icms', 'aliquota_icms', 'pis_cofins_cst', 'aliquota_pis', 'aliquota_cofins',
                'class_trib_cst', 'percentual_reducao_ibs', 'percentual_reducao_cbs',
            ]);
        });

        $sqlite->table('pdvs_cache', fn (Blueprint $table) => $table->dropColumn(['csc', 'csc_id']));

        $sqlite->dropIfExists('empresa_cache');
    }
};