<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mdfes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serie_mdfe_id')->nullable()->constrained('series_mdfe')->nullOnDelete();
            $table->unsignedSmallInteger('serie')->nullable();
            $table->unsignedInteger('numero')->nullable();
            $table->string('codigo_numerico', 8)->nullable();
            $table->string('status', 15)->default('rascunho'); // rascunho | emitido | encerrado | cancelado
            $table->unsignedTinyInteger('tipo_emitente')->default(2);      // tpEmit
            $table->unsignedTinyInteger('tipo_transportador')->nullable(); // tpTransp
            $table->foreignId('operador_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('uf_inicio', 2)->nullable();
            $table->string('uf_fim', 2)->nullable();
            $table->dateTime('inicio_viagem')->nullable();
            $table->string('rntrc', 8)->nullable();

            // Totais (recalculados a partir dos documentos)
            $table->unsignedSmallInteger('quantidade_nfe')->default(0);
            $table->decimal('valor_carga', 15, 2)->default(0);
            $table->decimal('peso_carga_kg', 15, 3)->default(0);

            $table->text('informacoes_adicionais')->nullable();
            $table->text('informacoes_fisco')->nullable();

            // Autorização
            $table->string('chave_acesso', 44)->nullable()->unique();
            $table->string('protocolo', 20)->nullable();
            $table->dateTime('emitido_em')->nullable();
            $table->longText('xml')->nullable();
            $table->text('motivo_rejeicao')->nullable();

            // Encerramento e cancelamento
            $table->dateTime('encerrado_em')->nullable();
            $table->string('protocolo_encerramento', 20)->nullable();
            $table->dateTime('cancelado_em')->nullable();
            $table->string('motivo_cancelamento', 255)->nullable();
            $table->string('protocolo_cancelamento', 20)->nullable();

            $table->timestamps();

            $table->index('status');
            $table->unique(['serie', 'numero']);
        });

        Schema::create('mdfe_percursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mdfe_id')->constrained('mdfes')->cascadeOnDelete();
            $table->string('uf', 2);
            $table->unsignedTinyInteger('ordem')->default(0);
        });

        Schema::create('mdfe_municipios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mdfe_id')->constrained('mdfes')->cascadeOnDelete();
            $table->string('tipo', 15); // carregamento | descarregamento
            $table->string('codigo_ibge', 7);
            $table->string('nome', 60);
            $table->string('uf', 2);
            $table->unsignedTinyInteger('ordem')->default(0);
        });

        Schema::create('mdfe_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mdfe_id')->constrained('mdfes')->cascadeOnDelete();
            $table->foreignId('mdfe_municipio_id')->constrained('mdfe_municipios')->cascadeOnDelete(); // município de descarga
            $table->string('tipo', 3)->default('nfe'); // nfe | cte (CT-e fica para depois)
            $table->foreignId('nota_fiscal_id')->nullable()->constrained('notas_fiscais')->nullOnDelete();
            $table->string('chave_acesso', 44);
            $table->decimal('valor', 15, 2)->default(0);
            $table->decimal('peso_bruto_kg', 15, 3)->default(0);

            $table->index('chave_acesso');
        });

        Schema::create('mdfe_veiculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mdfe_id')->constrained('mdfes')->cascadeOnDelete();
            $table->string('papel', 10); // tracao | reboque
            $table->foreignId('veiculo_id')->nullable()->constrained('veiculos')->nullOnDelete();
            $table->unsignedTinyInteger('ordem')->default(0);

            // Snapshot do cadastro no momento do MDF-e
            $table->string('placa', 7);
            $table->string('uf', 2);
            $table->string('rntrc', 8)->nullable();
            $table->unsignedInteger('tara_kg')->nullable();
            $table->unsignedInteger('capacidade_kg')->nullable();
            $table->unsignedInteger('capacidade_m3')->nullable();
            $table->string('tipo_rodado', 2)->nullable();
            $table->string('tipo_carroceria', 2)->nullable();

            // Proprietário (só quando o veículo não é do emitente)
            $table->unsignedTinyInteger('proprietario_tipo')->nullable(); // tpProp
            $table->string('proprietario_documento', 14)->nullable();
            $table->string('proprietario_nome', 60)->nullable();
            $table->string('proprietario_ie', 14)->nullable();
            $table->string('proprietario_uf', 2)->nullable();
            $table->string('proprietario_rntrc', 8)->nullable();
        });

        Schema::create('mdfe_condutores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mdfe_id')->constrained('mdfes')->cascadeOnDelete();
            $table->foreignId('mdfe_veiculo_id')->constrained('mdfe_veiculos')->cascadeOnDelete();
            $table->foreignId('condutor_id')->nullable()->constrained('condutores')->nullOnDelete();
            $table->string('nome', 60); // snapshot: editar o cadastro depois não altera o MDF-e emitido
            $table->string('cpf', 11);
        });

        Schema::create('mdfe_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mdfe_id')->constrained('mdfes')->cascadeOnDelete();
            $table->string('tipo', 25); // cancelamento | encerramento | inclusao_condutor | inclusao_dfe
            $table->string('codigo_evento', 6)->nullable();
            $table->unsignedTinyInteger('sequencia')->default(1);
            $table->string('justificativa', 255)->nullable();
            $table->json('dados')->nullable();
            $table->string('protocolo', 20)->nullable();
            $table->longText('xml')->nullable();
            $table->foreignId('operador_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('registrado_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mdfe_eventos');
        Schema::dropIfExists('mdfe_condutores');
        Schema::dropIfExists('mdfe_veiculos');
        Schema::dropIfExists('mdfe_documentos');
        Schema::dropIfExists('mdfe_municipios');
        Schema::dropIfExists('mdfe_percursos');
        Schema::dropIfExists('mdfes');
    }
};
