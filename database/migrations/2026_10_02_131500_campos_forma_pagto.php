<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Altera a estrutura da tabela inserindo as novas colunas
        Schema::table('formas_pagamento', function (Blueprint $table) {
            $table->char('meio_pagamento', 2)->default('99')->after('descricao'); // tPag da SEFAZ
            $table->tinyInteger('ind_pag')->default(0)->after('meio_pagamento');   // 0 = À vista, 1 = A prazo
        });

        // 2. Executa a migração e o melhor esforço dos dados existentes de forma segura
        DB::transaction(function () {
            $mapa = [
                'dinheiro'   => '01',
                'cheque'     => '02',
                'crédito'    => '03',
                'credito'    => '03',
                'débito'     => '04',
                'debito'     => '04',
                'boleto'     => '15',
                'depósito'   => '16',
                'deposito'   => '16',
                'pix'        => '17',
                'transfer'   => '18',
            ];

            // Processa em lotes (chunk) para evitar estouro de memória se a tabela for grande
            DB::table('formas_pagamento')->orderBy('id')->chunk(100, function ($formas) use ($mapa) {
                foreach ($formas as $forma) {
                    $descricao = mb_strtolower($forma->descricao);
                    $codigo = '99';

                    // Busca o código SEFAZ correspondente
                    foreach ($mapa as $palavra => $tpag) {
                        if (str_contains($descricao, $palavra)) {
                            $codigo = $tpag;
                            break;
                        }
                    }

                    // Define se o indicador padrão é a prazo
                    $indPag = str_contains($descricao, 'prazo') ? 1 : 0;

                    DB::table('formas_pagamento')
                        ->where('id', $forma->id)
                        ->update([
                            'meio_pagamento' => $codigo,
                            'ind_pag'        => $indPag,
                        ]);
                }
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('formas_pagamento', function (Blueprint $table) {
            $table->dropColumn(['meio_pagamento', 'ind_pag']);
        });
    }
};
