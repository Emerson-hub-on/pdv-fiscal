<?php

namespace Database\Seeders;

use App\Models\Ncm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class NcmSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Baixando tabela NCM da API oficial...');

        try {
            $response = Http::timeout(60)->get('https://brasilapi.com.br/api/ncm/v1');

            if (!$response->successful()) {
                $this->command->error('Falha ao acessar a API. Tente novamente.');
                return;
            }

            $ncms = $response->json();
        } catch (\Exception $e) {
            $this->command->error('Erro de conexão: ' . $e->getMessage());
            return;
        }

        // Remove NCMs inválidos já cadastrados (menos de 8 dígitos, desconsiderando pontos)
        $this->command->info('Removendo NCMs inválidos para NF-e...');
        $removidos = Ncm::where('cadastro_avulso', false)
            ->get()
            ->filter(fn($ncm) => strlen(str_replace('.', '', $ncm->codigo)) !== 8)
            ->each(fn($ncm) => $ncm->delete());
        $this->command->info("  {$removidos->count()} NCMs inválidos removidos.");

        // Importa apenas NCMs com 8 dígitos
        $this->command->info('Importando registros NCM válidos...');

        $total = 0;
        $chunks = array_chunk($ncms, 500);

        foreach ($chunks as $chunk) {
            $registros = array_map(fn($item) => [
                'codigo'          => str_replace('.', '', $item['codigo']),
                'descricao'       => $item['descricao'],
                'cadastro_avulso' => false,
                'created_at'      => now(),
                'updated_at'      => now(),
            ], $chunk);

            // Filtra apenas NCMs completos (8 dígitos) — capítulos/posições são rejeitados pelo SEFAZ
            $registros = array_values(array_filter(
                $registros,
                fn($r) => strlen($r['codigo']) === 8
            ));

            if (empty($registros)) continue;

            Ncm::upsert($registros, ['codigo'], ['descricao', 'updated_at']);
            $total += count($registros);
            $this->command->info("  {$total} registros processados...");
        }

        $this->command->info('✓ Importação concluída! ' . $total . ' NCMs importados.');
    }
}