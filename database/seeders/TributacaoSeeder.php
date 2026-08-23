<?php

namespace Database\Seeders;

use App\Models\Tributacao;
use Illuminate\Database\Seeder;

class TributacaoSeeder extends Seeder
{
    public function run(): void
    {
        $tributacoes = [
            // ===== SIMPLES NACIONAL (CRT 1 e 2) =====
            [
                'descricao' => 'Tributado SN - Sem permissão de crédito',
                'crt' => 1,
                'cfop' => '5102',
                'csosn' => '102',
                'cst_icms' => null,
                'aliquota_icms' => 0,
                'observacao' => 'Produto tributado pelo Simples Nacional. Uso mais comum para saída interna.',
            ],
            [
                'descricao' => 'Tributado SN - Com permissão de crédito',
                'crt' => 1,
                'cfop' => '5102',
                'csosn' => '101',
                'cst_icms' => null,
                'aliquota_icms' => 0,
                'observacao' => 'Produto tributado pelo SN com permissão de crédito ao destinatário.',
            ],
            [
                'descricao' => 'Substituição Tributária - ST já retida',
                'crt' => 1,
                'cfop' => '5405',
                'csosn' => '500',
                'cst_icms' => null,
                'aliquota_icms' => 0,
                'observacao' => 'ICMS-ST já recolhido anteriormente na cadeia (fabricante/distribuidor). Mais comum em bebidas, cigarros, combustíveis, autopeças.',
            ],
            [
                'descricao' => 'Substituição Tributária - ST cobrada anteriormente (CFOP 5403)',
                'crt' => 1,
                'cfop' => '5403',
                'csosn' => '400',
                'cst_icms' => null,
                'aliquota_icms' => 0,
                'observacao' => 'ICMS-ST cobrado anteriormente. Variação do ST com CFOP 5403.',
            ],
            [
                'descricao' => 'Isento / Não tributado SN',
                'crt' => 1,
                'cfop' => '5102',
                'csosn' => '300',
                'cst_icms' => null,
                'aliquota_icms' => 0,
                'observacao' => 'Produto isento ou não tributado pelo Simples Nacional.',
            ],
            [
                'descricao' => 'Imune SN',
                'crt' => 1,
                'cfop' => '5102',
                'csosn' => '900',
                'cst_icms' => null,
                'aliquota_icms' => 0,
                'observacao' => 'Outros - produto com imunidade tributária ou situação especial.',
            ],

            // ===== SIMPLES NACIONAL EXCESSO (CRT 2) =====
            [
                'descricao' => 'Tributado SN Excesso - Sem crédito',
                'crt' => 2,
                'cfop' => '5102',
                'csosn' => '102',
                'cst_icms' => null,
                'aliquota_icms' => 0,
                'observacao' => 'Empresa enquadrada no Simples Nacional com excesso de receita.',
            ],
            [
                'descricao' => 'ST já retida - SN Excesso',
                'crt' => 2,
                'cfop' => '5405',
                'csosn' => '500',
                'cst_icms' => null,
                'aliquota_icms' => 0,
                'observacao' => 'ST já recolhida - Simples Nacional com excesso de receita.',
            ],

            // ===== LUCRO PRESUMIDO / REAL (CRT 3) =====
            [
                'descricao' => 'Tributado Integralmente - 12%',
                'crt' => 3,
                'cfop' => '5102',
                'csosn' => null,
                'cst_icms' => '000',
                'aliquota_icms' => 12,
                'observacao' => 'ICMS tributado integralmente a 12% (alíquota interna padrão PB para muitos produtos).',
            ],
            [
                'descricao' => 'Tributado Integralmente - 18%',
                'crt' => 3,
                'cfop' => '5102',
                'csosn' => null,
                'cst_icms' => '000',
                'aliquota_icms' => 18,
                'observacao' => 'ICMS tributado integralmente a 18% (alíquota majorada para produtos supérfluos/não essenciais na PB).',
            ],
            [
                'descricao' => 'Tributado Integralmente - 20%',
                'crt' => 3,
                'cfop' => '5102',
                'csosn' => null,
                'cst_icms' => '000',
                'aliquota_icms' => 20,
                'observacao' => 'ICMS a 20% - produtos específicos com alíquota elevada (ex: energia elétrica, comunicação em alguns estados).',
            ],
            [
                'descricao' => 'Substituição Tributária - ST retida (CRT3)',
                'crt' => 3,
                'cfop' => '5405',
                'csosn' => null,
                'cst_icms' => '060',
                'aliquota_icms' => 0,
                'observacao' => 'ICMS-ST já retido por substituição - Regime Normal.',
            ],
            [
                'descricao' => 'Isento - CRT3',
                'crt' => 3,
                'cfop' => '5102',
                'csosn' => null,
                'cst_icms' => '040',
                'aliquota_icms' => 0,
                'observacao' => 'Produto isento de ICMS - Regime Normal.',
            ],
            [
                'descricao' => 'Não tributado - CRT3',
                'crt' => 3,
                'cfop' => '5102',
                'csosn' => null,
                'cst_icms' => '041',
                'aliquota_icms' => 0,
                'observacao' => 'Não tributado - saída de produto não sujeito ao ICMS.',
            ],
        ];

        foreach ($tributacoes as $t) {
            Tributacao::firstOrCreate(
                ['descricao' => $t['descricao'], 'crt' => $t['crt']],
                $t
            );
        }

        $this->command->info('✓ Tributações importadas com sucesso!');
    }
}