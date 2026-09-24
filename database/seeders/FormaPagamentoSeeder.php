<?php

namespace Database\Seeders;

use App\Models\FormaPagamento;
use Illuminate\Database\Seeder;

class FormaPagamentoSeeder extends Seeder
{
    public function run(): void
    {
        $formas = ['À vista', 'Pix', 'Boleto', 'A prazo', 'A prazo (30, 60, 90)'];

        foreach ($formas as $ordem => $descricao) {
            FormaPagamento::updateOrCreate(['descricao' => $descricao], ['ordem' => $ordem + 1]);
        }
    }
}