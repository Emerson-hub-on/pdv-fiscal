<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Uma linha por DPS enviada: é a trilha de auditoria e o que impede perder número ou duplicar nota
        Schema::create('nfse_emissoes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('ordem_servico_id')->nullable()->index();
            $t->unsignedBigInteger('servico_id')->nullable();
            $t->unsignedTinyInteger('ambiente');                 // 1 = produção, 2 = homologação
            $t->string('serie', 5);
            $t->unsignedBigInteger('numero');                     // nDPS
            $t->string('dps_id', 45);                             // Id da infDPS
            $t->string('status', 12)->default('enviando')->index(); // enviando | autorizada | rejeitada | indefinida
            $t->decimal('valor_servico', 12, 2)->default(0);
            $t->longText('xml_dps');                              // DPS assinada, exatamente como foi enviada
            $t->longText('xml_nfse')->nullable();
            $t->string('chave_acesso', 50)->nullable()->index();
            $t->string('numero_nfse', 20)->nullable();
            $t->text('mensagem')->nullable();                     // erros da Sefin ou motivo do resultado
            $t->timestamp('enviada_em')->nullable();
            $t->timestamp('autorizada_em')->nullable();
            $t->timestamps();

            $t->unique(['ambiente', 'dps_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfse_emissoes');
    }
};
