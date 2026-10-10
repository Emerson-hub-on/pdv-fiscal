<?php

namespace Database\Seeders;

use App\Models\CfopEntrada;
use App\Models\CfopEntradaConversao;
use App\Models\CstEntradaConversao;
use App\Models\OperacaoEntrada;
use Illuminate\Database\Seeder;

class EntradaFiscalSeeder extends Seeder
{
    public function run(): void
    {
        $this->cfopsEntrada();
        $this->operacoesEConversoesCfop();
        $this->conversoesCst();
    }

    private function cfopsEntrada(): void
    {
        $base = [
            '101' => 'Compra para industrialização ou produção rural',
            '102' => 'Compra para comercialização',
            '401' => 'Compra para industrialização ou produção rural em operação com mercadoria sujeita ao regime de substituição tributária',
            '403' => 'Compra para comercialização em operação com mercadoria sujeita ao regime de substituição tributária',
            '406' => 'Compra de bem para o ativo imobilizado cuja mercadoria está sujeita ao regime de substituição tributária',
            '407' => 'Compra de mercadoria para uso ou consumo cuja mercadoria está sujeita ao regime de substituição tributária',
            '551' => 'Compra de bem para o ativo imobilizado',
            '556' => 'Compra de material para uso ou consumo',
            '910' => 'Entrada de bonificação, doação ou brinde',
            '911' => 'Entrada de amostra grátis',
            '949' => 'Outra entrada de mercadoria ou prestação de serviço não especificada',
        ];

        // 1 = operação dentro do estado, 2 = operação de outros estados
        foreach (['1', '2'] as $area) {
            foreach ($base as $sufixo => $descricao) {
                CfopEntrada::firstOrCreate(
                    ['codigo' => $area . $sufixo],
                    ['descricao' => $descricao, 'ativo' => true]
                );
            }
        }
    }

    private function operacoesEConversoesCfop(): void
    {
        // CFOPs que o fornecedor usa na saída, por grupo
        $origens = [
            'venda' => [
                '5101', '5102', '5103', '5104', '5105', '5106', '5109', '5110',
                '5116', '5117', '5118', '5119', '5120', '5122', '5123', '5551',
                '6101', '6102', '6103', '6104', '6105', '6106', '6107', '6108', '6109', '6110',
                '6116', '6117', '6118', '6119', '6120', '6122', '6123', '6551',
            ],
            'st'          => ['5401', '5402', '5403', '5404', '5405', '6401', '6402', '6403', '6404', '6405'],
            'bonificacao' => ['5910', '6910'],
            'amostra'     => ['5911', '6911'],
            'outras'      => ['5949', '6949'],
        ];

        // [codigo, descricao, movimenta_estoque, ordem, sufixo, sufixo_com_st, grupos de origem aceitos]
        $operacoes = [
            ['compra_comercializacao',   'Compra para comercialização',                    true,  1, '102', '403', ['venda', 'st']],
            ['compra_industrializacao',  'Compra para industrialização ou produção rural', true,  2, '101', '401', ['venda', 'st']],
            ['compra_uso_consumo',       'Compra de material para uso ou consumo',         false, 3, '556', '407', ['venda', 'st']],
            ['compra_ativo_imobilizado', 'Compra de bem para o ativo imobilizado',         false, 4, '551', '406', ['venda', 'st']],
            ['bonificacao',              'Entrada de bonificação, doação ou brinde',       true,  5, '910', '910', ['venda', 'st', 'bonificacao', 'outras']],
            ['amostra_gratis',           'Entrada de amostra grátis',                      false, 6, '911', '911', ['venda', 'st', 'amostra', 'outras']],
            ['outras_entradas',          'Outras entradas não especificadas',              true,  7, '949', '949', ['venda', 'st', 'bonificacao', 'amostra', 'outras']],
        ];

        $cfops = CfopEntrada::pluck('id', 'codigo');

        foreach ($operacoes as [$codigo, $descricao, $movimenta, $ordem, $sufixo, $sufixoSt, $grupos]) {
            $operacao = OperacaoEntrada::firstOrCreate(
                ['codigo' => $codigo],
                ['descricao' => $descricao, 'movimenta_estoque' => $movimenta, 'ordem' => $ordem, 'ativo' => true]
            );

            foreach ($grupos as $grupo) {
                foreach ($origens[$grupo] as $cfopOrigem) {
                    // 5xxx (dentro do estado) -> 1xxx ; 6xxx (outro estado) -> 2xxx
                    $area = $cfopOrigem[0] === '5' ? '1' : '2';
                    $cfopEntrada = $area . ($grupo === 'st' ? $sufixoSt : $sufixo);

                    CfopEntradaConversao::firstOrCreate(
                        ['operacao_entrada_id' => $operacao->id, 'cfop_origem' => $cfopOrigem],
                        ['cfop_entrada_id' => $cfops[$cfopEntrada]]
                    );
                }
            }
        }
    }

    private function conversoesCst(): void
    {
        // [regime, tipo_origem, codigo_origem, codigo_entrada, gera_credito]
        // SUGESTÕES: validar com o contador antes de usar em produção.
        $linhas = [
            // Empresa no Simples Nacional -> entrada em CSOSN (sem crédito de ICMS)
            ['simples', 'cst', '00', '102', false],
            ['simples', 'cst', '10', '500', false],
            ['simples', 'cst', '20', '102', false],
            ['simples', 'cst', '30', '500', false],
            ['simples', 'cst', '40', '400', false],
            ['simples', 'cst', '41', '400', false],
            ['simples', 'cst', '50', '400', false],
            ['simples', 'cst', '51', '900', false],
            ['simples', 'cst', '60', '500', false],
            ['simples', 'cst', '70', '500', false],
            ['simples', 'cst', '90', '900', false],
            ['simples', 'csosn', '101', '102', false],
            ['simples', 'csosn', '102', '102', false],
            ['simples', 'csosn', '103', '400', false],
            ['simples', 'csosn', '201', '500', false],
            ['simples', 'csosn', '202', '500', false],
            ['simples', 'csosn', '203', '500', false],
            ['simples', 'csosn', '300', '300', false],
            ['simples', 'csosn', '400', '400', false],
            ['simples', 'csosn', '500', '500', false],
            ['simples', 'csosn', '900', '900', false],

            // Empresa no Regime Normal -> entrada em CST
            ['normal', 'cst', '00', '00', true],
            ['normal', 'cst', '10', '60', false],
            ['normal', 'cst', '20', '20', true],
            ['normal', 'cst', '30', '60', false],
            ['normal', 'cst', '40', '40', false],
            ['normal', 'cst', '41', '41', false],
            ['normal', 'cst', '50', '50', false],
            ['normal', 'cst', '51', '51', false],
            ['normal', 'cst', '60', '60', false],
            ['normal', 'cst', '70', '60', false],
            ['normal', 'cst', '90', '90', false],
            ['normal', 'csosn', '101', '90', true],
            ['normal', 'csosn', '102', '90', false],
            ['normal', 'csosn', '103', '40', false],
            ['normal', 'csosn', '201', '60', false],
            ['normal', 'csosn', '202', '60', false],
            ['normal', 'csosn', '203', '60', false],
            ['normal', 'csosn', '300', '40', false],
            ['normal', 'csosn', '400', '41', false],
            ['normal', 'csosn', '500', '60', false],
            ['normal', 'csosn', '900', '90', false],
        ];

        foreach ($linhas as [$regime, $tipo, $origem, $entrada, $credito]) {
            CstEntradaConversao::firstOrCreate(
                ['regime' => $regime, 'tipo_origem' => $tipo, 'codigo_origem' => $origem],
                ['codigo_entrada' => $entrada, 'gera_credito' => $credito]
            );
        }
    }
}