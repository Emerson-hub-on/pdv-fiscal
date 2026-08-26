<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Semeia os 7 codigos oficiais de CST IPI de SAIDA (Tabela SPED,
 * mesma familia da tabela CST PIS/COFINS - IN RFB 1.009/2010, estavel).
 *
 * So cobre saida (50-55, 99) porque este sistema so emite NFC-e de venda.
 *
 * cEnq vem com "999" (tributacao normal, sem enquadramento especifico) por
 * padrao - cobre a esmagadora maioria dos casos de comercio nao-industrial.
 * Aliquota fica NULA de proposito, exceto onde o CST exige (50 - tributada) -
 * ai sim o usuario preenche a aliquota real do produto, se for o caso.
 */
class ClassificacaoIpiSeeder extends Seeder
{
    public function run(): void
    {
        $codigos = [
            ['codigo' => '50', 'descricao' => 'Saída Tributada'],
            ['codigo' => '51', 'descricao' => 'Saída Tributável com Alíquota Zero'],
            ['codigo' => '52', 'descricao' => 'Saída Isenta'],
            ['codigo' => '53', 'descricao' => 'Saída Não-Tributada'],
            ['codigo' => '54', 'descricao' => 'Saída Imune'],
            ['codigo' => '55', 'descricao' => 'Saída com Suspensão'],
            ['codigo' => '99', 'descricao' => 'Outras Saídas'],
        ];

        $now = now();

        foreach ($codigos as $item) {
            DB::table('classificacoes_ipi')->updateOrInsert(
                ['codigo' => $item['codigo'], 'cenq' => '999', 'aliquota' => null],
                [
                    'descricao' => $item['descricao'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $this->command->info(count($codigos) . ' códigos CST de IPI (saída) semeados.');
    }
}