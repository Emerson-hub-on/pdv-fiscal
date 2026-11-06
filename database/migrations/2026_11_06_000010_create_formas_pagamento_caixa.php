<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Formas de pagamento do caixa (Cadastros → Caixa → Formas de pagamento). Antes eram fixas no código;
 * as 6 de sempre entram como "do sistema", com os mesmos códigos já gravados nas vendas.
 * - codigo: o que vai em venda_pagamentos.forma_pagamento (string 20)
 * - meio_pagamento: tPag da NFC-e (01 dinheiro, 03 crédito, 04 débito, 17 PIX...)
 * - tipo: comportamento especial no caixa (credito_loja e convenio abrem os modais próprios)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formas_pagamento_caixa', function (Blueprint $t) {
            $t->id();
            $t->string('codigo', 20)->unique();
            $t->string('descricao', 60);
            $t->string('meio_pagamento', 2);
            $t->string('tipo', 20)->default('outro'); // dinheiro | pix | credito | debito | credito_loja | convenio | outro
            $t->boolean('permite_troco')->default(false);
            $t->boolean('ativo')->default(true);
            $t->unsignedSmallInteger('ordem')->default(0);
            $t->boolean('sistema')->default(false);
            $t->timestamps();
        });

        $agora = now();
        foreach (\App\Support\FormasCaixa::PADRAO as $i => $f) {
            DB::table('formas_pagamento_caixa')->insert($f + [
                'ativo' => true, 'ordem' => ($i + 1) * 10, 'sistema' => true, 'created_at' => $agora, 'updated_at' => $agora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('formas_pagamento_caixa');
    }
};
