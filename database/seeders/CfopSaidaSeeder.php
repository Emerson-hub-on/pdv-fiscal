<?php

namespace Database\Seeders;

use App\Models\CfopSaida;
use Illuminate\Database\Seeder;

class CfopSaidaSeeder extends Seeder
{
    public function run(): void
    {
        // Cada linha é um par: [codigo interno, codigo interestadual, descrição base,
        // natureza da operação, finalidade, movimenta estoque]
        $pares = [
            ['5101', '6101', 'Venda de produção do estabelecimento', 'Venda de produção própria', 1, true],
            ['5102', '6102', 'Venda de mercadoria adquirida ou recebida de terceiros', 'Venda de mercadoria', 1, true],
            ['5405', '6405', 'Venda de mercadoria sujeita ao regime de substituição tributária, na condição de substituído', 'Venda de mercadoria com ICMS-ST retido anteriormente', 1, true],
            ['5202', '6202', 'Devolução de compra para comercialização', 'Devolução de mercadoria ao fornecedor', 4, true],
            ['5152', '6152', 'Transferência de mercadoria adquirida ou recebida de terceiros', 'Transferência de mercadoria entre filiais', 1, true],
            ['5910', '6910', 'Remessa em bonificação, doação ou brinde', 'Bonificação/doação de mercadoria', 1, true],
            ['5911', '6911', 'Remessa de amostra grátis', 'Remessa de amostra grátis', 1, true],
            ['5915', '6915', 'Remessa de mercadoria ou bem para conserto ou reparo', 'Remessa para conserto/reparo', 1, false],
            ['5916', '6916', 'Retorno de mercadoria ou bem recebido para conserto ou reparo', 'Retorno de mercadoria após conserto/reparo', 1, false],
            ['5949', '6949', 'Outra saída de mercadoria ou prestação de serviço não especificado', 'Outras saídas não especificadas', 1, true],
        ];

        $ordem = 1;

        foreach ($pares as [$codigoEstadual, $codigoInterestadual, $descricao, $natureza, $finalidade, $movimenta]) {
            CfopSaida::updateOrCreate(
                ['codigo' => $codigoEstadual],
                [
                    'descricao' => "{$descricao} (dentro do estado)",
                    'natureza_operacao_padrao' => $natureza,
                    'finalidade_padrao' => $finalidade,
                    'movimenta_estoque' => $movimenta,
                    'ordem' => $ordem++,
                ]
            );

            CfopSaida::updateOrCreate(
                ['codigo' => $codigoInterestadual],
                [
                    'descricao' => "{$descricao} (outro estado)",
                    'natureza_operacao_padrao' => $natureza,
                    'finalidade_padrao' => $finalidade,
                    'movimenta_estoque' => $movimenta,
                    'ordem' => $ordem++,
                ]
            );
        }
    }
}