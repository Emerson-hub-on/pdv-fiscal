<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Semeia os 10 códigos oficiais de CST PIS/COFINS de SAÍDA
 * (Tabelas 4.3.3 e 4.3.4 do SPED — IN RFB nº 1.009/2010, estável desde então,
 * sem mudanças de código conhecidas até a data deste seeder).
 *
 * Só cobre saída (01-09, 49) porque este sistema só emite NFC-e de venda —
 * os códigos de entrada (50-66, 70-99) não se aplicam aqui.
 *
 * As alíquotas ficam NULAS de propósito: elas dependem do regime de apuração
 * da empresa (cumulativo vs não-cumulativo) e de particularidades do produto
 * (monofásico, etc.) — não são um dado público fixo como o código em si.
 * CONFIRME COM SEU CONTADOR as alíquotas antes de preencher e usar em produção.
 */
class ClassificacaoPisCofinsSeeder extends Seeder
{
    public function run(): void
    {
        $codigos = [
            ['codigo' => '01', 'descricao' => 'Operação Tributável com Alíquota Básica'],
            ['codigo' => '02', 'descricao' => 'Operação Tributável com Alíquota Diferenciada'],
            ['codigo' => '03', 'descricao' => 'Operação Tributável com Alíquota por Unidade de Medida de Produto'],
            ['codigo' => '04', 'descricao' => 'Operação Tributável Monofásica - Revenda a Alíquota Zero'],
            ['codigo' => '05', 'descricao' => 'Operação Tributável por Substituição Tributária'],
            ['codigo' => '06', 'descricao' => 'Operação Tributável a Alíquota Zero'],
            ['codigo' => '07', 'descricao' => 'Operação Isenta da Contribuição'],
            ['codigo' => '08', 'descricao' => 'Operação sem Incidência da Contribuição'],
            ['codigo' => '09', 'descricao' => 'Operação com Suspensão da Contribuição'],
            ['codigo' => '49', 'descricao' => 'Outras Operações de Saída'],
        ];

        $now = now();

        foreach ($codigos as $item) {
            DB::table('classificacoes_pis_cofins')->updateOrInsert(
                ['codigo' => $item['codigo'], 'aliquota_pis' => null, 'aliquota_cofins' => null],
                [
                    'descricao' => $item['descricao'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $this->command->info(count($codigos) . ' códigos CST de PIS/COFINS (saída) semeados. Alíquotas ficaram em branco — preencha via modal, com seu contador, antes de usar em produção.');
    }
}