<?php

namespace Database\Seeders;

use App\Models\TipoContaPagar;
use Illuminate\Database\Seeder;

class TipoContaPagarSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            'Fornecedor / Compra de mercadoria',
            'Aluguel',
            'Energia elétrica',
            'Água e esgoto',
            'Internet / Telefone',
            'Impostos e taxas',
            'Folha de pagamento',
            'Frete',
            'Manutenção',
            'Outros',
        ];

        foreach ($tipos as $descricao) {
            TipoContaPagar::firstOrCreate(['descricao' => $descricao]);
        }
    }
}
