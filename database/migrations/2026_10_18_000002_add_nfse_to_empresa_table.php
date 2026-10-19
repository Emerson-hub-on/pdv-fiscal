<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa', function (Blueprint $t) {
            // Ambiente próprio da NFS-e: dá para testar em homologação com a NF-e/NFC-e já em produção.
            // Mesma convenção do campo "ambiente": 1 = produção, 2 = homologação.
            $t->unsignedTinyInteger('ambiente_nfse')->default(2)->after('ambiente');

            // Numeração da DPS (Declaração de Prestação de Serviços): série e último número já usado.
            $t->string('serie_dps', 5)->default('1')->after('ambiente_nfse');
            $t->unsignedBigInteger('ultimo_numero_dps')->default(0)->after('serie_dps');

            // Complementam o CRT, que não distingue MEI de ME/EPP nem informa regime especial de tributação
            $t->boolean('nfse_mei')->default(false)->after('ultimo_numero_dps');
            $t->unsignedTinyInteger('nfse_regime_especial')->default(0)->after('nfse_mei');
        });
    }

    public function down(): void
    {
        Schema::table('empresa', function (Blueprint $t) {
            $t->dropColumn(['ambiente_nfse', 'serie_dps', 'ultimo_numero_dps', 'nfse_mei', 'nfse_regime_especial']);
        });
    }
};
