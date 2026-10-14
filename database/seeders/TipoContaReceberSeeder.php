<?php

namespace Database\Seeders;

use App\Models\TipoContaReceber;
use Illuminate\Database\Seeder;

class TipoContaReceberSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            'Venda a prazo / Crediário',
            'Serviço prestado',
            'Aluguel recebido',
            'Comissão',
            'Adiantamento de cliente',
            'Outros',
        ];

        foreach ($tipos as $descricao) {
            TipoContaReceber::firstOrCreate(['descricao' => $descricao]);
        }
    }
}
