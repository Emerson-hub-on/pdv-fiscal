<?php

namespace Database\Seeders;

use App\Models\Cest;
use App\Models\CestNcm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Importa a tabela oficial de CEST (Convênio ICMS 142/18).
 *
 * ANTES DE RODAR:
 * 1. Baixe os CSVs oficiais:
 *    - https://tabelasfiscais.com.br/public/downloads/cest.csv
 *    - https://tabelasfiscais.com.br/public/downloads/cest_ncm.csv
 *    (ou a fonte que preferir, ex. fórum ACBr / CONFAZ)
 * 2. Salve como storage/app/fiscal/cest.csv e storage/app/fiscal/cest_ncm.csv
 * 3. Abra o CSV e confira o cabeçalho — o separador é ";". Ajuste os nomes
 *    de coluna no mapeamento abaixo ($col) se vierem diferentes do esperado.
 * 4. php artisan db:seed --class=CestSeeder
 */
class CestSeeder extends Seeder
{
    public function run(): void
    {
        $this->importCests();
        $this->importCestNcm();
    }

    private function importCests(): void
    {
        $path = storage_path('app/fiscal/cest.csv');

        if (! file_exists($path)) {
            $this->command->warn("Arquivo não encontrado: {$path}. Pulei a importação de CEST.");
            return;
        }

        $handle = fopen($path, 'r');

        // Remove o BOM UTF-8, se existir, antes de ler o cabeçalho
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle, 0, ';');
        $header = array_map(fn ($h) => strtolower(trim($h)), $header);

        // Ajuste aqui se o nome das colunas no CSV vier diferente
        $col = [
            'codigo'    => $this->findColumn($header, ['cest', 'codigo', 'codigo_cest']),
            'descricao' => $this->findColumn($header, ['descricao', 'descricao_cest', 'desc']),
            'segmento'  => $this->findColumn($header, ['segmento', 'segmento_codigo']),
            'segmento_descricao' => $this->findColumn($header, ['segmento_descricao', 'descricao_segmento']),
        ];

        $rows = [];
        $now = now();

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $codigo = preg_replace('/\D/', '', $data[$col['codigo']] ?? '');
            if (strlen($codigo) !== 7) {
                continue; // ignora linhas de cabeçalho de anexo/segmento sem código válido
            }

            $rows[] = [
                'codigo'              => $codigo,
                'descricao'           => trim($data[$col['descricao']] ?? ''),
                'segmento_codigo'     => substr($codigo, 0, 2),
                'segmento_descricao'  => $col['segmento_descricao'] !== null
                    ? trim($data[$col['segmento_descricao']] ?? '')
                    : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        fclose($handle);

        collect($rows)->chunk(300)->each(function ($chunk) {
            DB::table('cests')->upsert(
                $chunk->toArray(),
                ['codigo'],
                ['descricao', 'segmento_codigo', 'segmento_descricao', 'updated_at']
            );
        });

        $this->command->info(count($rows) . ' códigos CEST importados.');
    }

    private function importCestNcm(): void
    {
        $path = storage_path('app/fiscal/cest_ncm.csv');

        if (! file_exists($path)) {
            $this->command->warn("Arquivo não encontrado: {$path}. Pulei o mapeamento CEST x NCM.");
            return;
        }

        $cestIdsPorCodigo = Cest::pluck('id', 'codigo');

        $handle = fopen($path, 'r');

        // Remove o BOM UTF-8, se existir, antes de ler o cabeçalho
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle, 0, ';');
        $header = array_map(fn ($h) => strtolower(trim($h)), $header);

        $col = [
            'cest' => $this->findColumn($header, ['cest', 'codigo_cest']),
            'ncm'  => $this->findColumn($header, ['ncm', 'codigo_ncm']),
        ];

        $rows = [];
        $now = now();

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $codigoCest = preg_replace('/\D/', '', $data[$col['cest']] ?? '');
            $ncm = preg_replace('/\D/', '', $data[$col['ncm']] ?? '');
            $cestId = $cestIdsPorCodigo[$codigoCest] ?? null;

            if (! $cestId || $ncm === '') {
                continue;
            }

            $rows[] = [
                'cest_id'    => $cestId,
                'ncm'        => $ncm,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        fclose($handle);

        collect($rows)->chunk(300)->each(function ($chunk) {
            DB::table('cest_ncm')->upsert(
                $chunk->toArray(),
                ['cest_id', 'ncm'],
                ['updated_at']
            );
        });

        $this->command->info(count($rows) . ' relações CEST x NCM importadas.');
    }

    private function findColumn(array $header, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $index = array_search($candidate, $header, true);
            if ($index !== false) {
                return $index;
            }
        }
        return null;
    }
}