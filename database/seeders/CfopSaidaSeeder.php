<?php

namespace Database\Seeders;

use App\Models\CfopSaida;
use Illuminate\Database\Seeder;

class CfopSaidaSeeder extends Seeder
{
    public function run(): void
    {
        $cfops = [
            [
                'codigo' => '5101',
                'descricao' => 'Venda de produção do estabelecimento',
                'natureza_operacao_padrao' => 'Venda de produção própria',
                'finalidade_padrao' => 1,
                'movimenta_estoque' => true,
            ],
            [
                'codigo' => '5102',
                'descricao' => 'Venda de mercadoria adquirida ou recebida de terceiros',
                'natureza_operacao_padrao' => 'Venda de mercadoria',
                'finalidade_padrao' => 1,
                'movimenta_estoque' => true,
            ],
            [
                'codigo' => '5405',
                'descricao' => 'Venda de mercadoria adquirida ou recebida de terceiros sujeita ao regime de substituição tributária, na condição de substituído',
                'natureza_operacao_padrao' => 'Venda de mercadoria com ICMS-ST retido anteriormente',
                'finalidade_padrao' => 1,
                'movimenta_estoque' => true,
            ],
            [
                'codigo' => '5202',
                'descricao' => 'Devolução de compra para comercialização',
                'natureza_operacao_padrao' => 'Devolução de mercadoria ao fornecedor',
                'finalidade_padrao' => 4,
                'movimenta_estoque' => true,
            ],
            [
                'codigo' => '5152',
                'descricao' => 'Transferência de mercadoria adquirida ou recebida de terceiros',
                'natureza_operacao_padrao' => 'Transferência de mercadoria entre filiais',
                'finalidade_padrao' => 1,
                'movimenta_estoque' => true,
            ],
            [
                'codigo' => '5910',
                'descricao' => 'Remessa em bonificação, doação ou brinde',
                'natureza_operacao_padrao' => 'Bonificação/doação de mercadoria',
                'finalidade_padrao' => 1,
                'movimenta_estoque' => true,
            ],
            [
                'codigo' => '5911',
                'descricao' => 'Remessa de amostra grátis',
                'natureza_operacao_padrao' => 'Remessa de amostra grátis',
                'finalidade_padrao' => 1,
                'movimenta_estoque' => true,
            ],
            [
                'codigo' => '5915',
                'descricao' => 'Remessa de mercadoria ou bem para conserto ou reparo',
                'natureza_operacao_padrao' => 'Remessa para conserto/reparo',
                'finalidade_padrao' => 1,
                'movimenta_estoque' => false,
            ],
            [
                'codigo' => '5916',
                'descricao' => 'Retorno de mercadoria ou bem recebido para conserto ou reparo',
                'natureza_operacao_padrao' => 'Retorno de mercadoria após conserto/reparo',
                'finalidade_padrao' => 1,
                'movimenta_estoque' => false,
            ],
            [
                'codigo' => '5949',
                'descricao' => 'Outra saída de mercadoria ou prestação de serviço não especificado',
                'natureza_operacao_padrao' => 'Outras saídas não especificadas',
                'finalidade_padrao' => 1,
                'movimenta_estoque' => true,
            ],
        ];

        foreach ($cfops as $cfop) {
            CfopSaida::updateOrCreate(['codigo' => $cfop['codigo']], $cfop);
        }
    }
}