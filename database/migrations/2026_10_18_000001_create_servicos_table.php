<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Serviços (NFS-e). Tabela própria: não mistura com produtos (sem NCM, estoque, EAN, variações)
        // e não entra no espelho do caixa, na entrada de nota nem na busca de peças da OS.
        Schema::create('servicos', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('codigo')->unique();

            $t->string('nome', 150)->index();
            $t->text('descricao')->nullable();              // texto padrão da discriminação do serviço na nota
            $t->decimal('valor_padrao', 12, 2)->default(0); // sugestão de preço ao lançar o serviço

            // Dados fiscais da NFS-e (padrão nacional)
            $t->string('codigo_tributacao_nacional', 6);    // item/subitem da LC 116 + desdobro nacional (só dígitos)
            $t->string('codigo_tributacao_municipal', 20)->nullable();
            $t->string('cnae', 7)->nullable();
            $t->unsignedTinyInteger('tributacao_issqn')->default(1); // 1 tributável, 2 imunidade, 3 exportação, 4 não incidência
            $t->decimal('aliquota_iss', 5, 2)->nullable();  // % (depende do regime da empresa e do município)

            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicos');
    }
};
