<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Importa a Tabela de Classificação Tributária IBS/CBS (cClassTrib + CST).
 * Fonte oficial: Portal Nacional de DF-e — Informe Técnico RT 2025.002.
 *
 * ANTES DE RODAR:
 * 1. Acesse https://dfe-portal.svrs.rs.gov.br/DFE/ClassificacaoTributaria
 * 2. Use a opção de exportar em CSV (botão "CSV" na página).
 * 3. Salve o arquivo como storage/app/fiscal/classificacao_tributaria.csv
 * 4. Abra o CSV e confira o cabeçalho — ajuste o mapeamento de colunas ($col)
 *    abaixo se os nomes vierem diferentes do esperado. Separador pode ser
 *    "," ou ";" dependendo da exportação — ajuste $delimitador se precisar.
 * 5. php artisan db:seed --class=ClassificacaoTributariaSeeder
 */
class ClassificacaoTributariaSeeder extends Seeder
{
    private string $delimitador = ';';

    public function run(): void
    {
        $path = storage_path('app/fiscal/classificacao_tributaria.csv');

        if (! file_exists($path)) {
            $this->command->warn("Arquivo não encontrado: {$path}. Nada foi importado.");
            return;
        }

        $handle = fopen($path, 'r');

        // Remove o BOM UTF-8, se existir, antes de ler o cabeçalho
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle, 0, $this->delimitador);

        // Se só leu 1 coluna, o delimitador provavelmente é o outro — tenta de novo
        if (count($header) === 1) {
            rewind($handle);
            fread($handle, 3); // BOM de novo
            $this->delimitador = $this->delimitador === ';' ? ',' : ';';
            $header = fgetcsv($handle, 0, $this->delimitador);
        }

        $header = array_map(fn ($h) => mb_strtolower(trim($h), 'UTF-8'), $header);

        // Ajuste aqui se o nome das colunas no CSV vier diferente
        $col = [
            'codigo'        => $this->findColumn($header, [
                'código da classificação tributária', 'codigo da classificacao tributaria',
                'cclasstrib', 'codigo', 'codigo_classificacao_tributaria',
            ]),
            'descricao'     => $this->findColumn($header, [
                'descrição do código da classificação tributária', 'descricao do codigo da classificacao tributaria',
                'descricao', 'descrição', 'descricao_cclasstrib',
            ]),
            'cst_codigo'    => $this->findColumn($header, [
                'código da situação tributária', 'codigo da situacao tributaria',
                'cst', 'cst_codigo', 'codigo_cst',
            ]),
            'cst_descricao' => $this->findColumn($header, [
                'descrição da situação tributária', 'descricao da situacao tributaria',
                'cst_descricao', 'descricao_cst',
            ]),
        ];

        if ($col['codigo'] === null || $col['descricao'] === null) {
            $this->command->error(
                'Não consegui identificar as colunas de código/descrição no CSV. ' .
                'Cabeçalho encontrado: ' . implode(' | ', $header) .
                '. Ajuste o mapeamento $col no seeder.'
            );
            fclose($handle);
            return;
        }

        $rows = [];
        $now = now();

        while (($data = fgetcsv($handle, 0, $this->delimitador)) !== false) {
            $codigo = preg_replace('/\D/', '', $data[$col['codigo']] ?? '');
            if (strlen($codigo) !== 6) {
                continue; // ignora linhas de cabeçalho de grupo/CST sem cClassTrib válido
            }

            $rows[] = [
                'codigo'        => $codigo,
                'descricao'     => trim($data[$col['descricao']] ?? ''),
                'cst_codigo'    => $col['cst_codigo'] !== null
                    ? preg_replace('/\D/', '', $data[$col['cst_codigo']] ?? '') ?: substr($codigo, 0, 3)
                    : substr($codigo, 0, 3),
                'cst_descricao' => $col['cst_descricao'] !== null
                    ? trim($data[$col['cst_descricao']] ?? '')
                    : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        fclose($handle);

        collect($rows)->chunk(300)->each(function ($chunk) {
            DB::table('classificacoes_tributarias')->upsert(
                $chunk->toArray(),
                ['codigo'],
                ['descricao', 'cst_codigo', 'cst_descricao', 'updated_at']
            );
        });

        $this->command->info(count($rows) . ' códigos de Classificação Tributária IBS/CBS importados.');
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