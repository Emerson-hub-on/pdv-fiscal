<?php

namespace Database\Seeders;

use App\Models\FormaPagamento;
use Illuminate\Database\Seeder;

class FormaPagamentoEntradaSeeder extends Seeder
{
    public function run(): void
    {
        // [descricao, tPag, ind_pag (0 = à vista, 1 = a prazo)] — formas usadas só na compra
        $formas = [
            ['Boleto a prazo',              '15', 1],
            ['Duplicata mercantil',         '14', 1],
            ['Transferência bancária (TED)', '18', 0],
            ['Pix estático',                '20', 0],
            ['Pix automático',              '23', 1],
            ['Cheque',                      '02', 0],
            ['Depósito bancário',           '16', 0],
            ['Pagamento posterior',         '91', 1],
            ['Sem pagamento',               '90', 0],
        ];

        foreach ($formas as [$descricao, $meio, $indPag]) {
            FormaPagamento::firstOrCreate(
                ['descricao' => $descricao],
                [
                    'meio_pagamento' => $meio,
                    'ind_pag'        => $indPag,
                    'ordem'          => (FormaPagamento::max('ordem') ?? 0) + 1,
                    'ativo'          => true,
                    'uso_saida'      => false,
                    'uso_entrada'    => true,
                ]
            );
        }
    }
}