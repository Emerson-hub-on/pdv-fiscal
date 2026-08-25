<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Importa a Tabela de Classificação Tributária IBS/CBS (cClassTrib + CST),
 * agora incluindo os percentuais de redução de alíquota (essenciais pra
 * itens da cesta básica e outras categorias com direito a redução).
 *
 * Fonte oficial: Portal Nacional de DF-e — Conformidade Fácil.
 * https://dfe-portal.svrs.rs.gov.br/Cff/ClassificacaoTributaria
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

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle, 0, $this->delimitador);

        if (count($header) === 1) {
            rewind($handle);
            fread($handle, 3);
            $this->delimitador = $this->delimitador === ';' ? ',' : ';';
            $header = fgetcsv($handle, 0, $this->delimitador);
        }

        $header = array_map(fn ($h) => mb_strtolower(trim($h), 'UTF-8'), $header);

        $col = [
            'codigo'        => $this->findColumn($header, [
                'código da classificação tributária', 'codigo da classificacao tributaria',
            ]),
            'descricao'     => $this->findColumn($header, [
                'descrição do código da classificação tributária', 'descricao do codigo da classificacao tributaria',
            ]),
            'cst_codigo'    => $this->findColumn($header, [
                'código da situação tributária', 'codigo da situacao tributaria',
            ]),
            'cst_descricao' => $this->findColumn($header, [
                'descrição da situação tributária', 'descricao da situacao tributaria',
            ]),
            'reducao_ibs'   => $this->findColumn($header, [
                'percentual redução ibs', 'percentual reducao ibs',
            ]),
            'reducao_cbs'   => $this->findColumn($header, [
                'percentual redução cbs', 'percentual reducao cbs',
            ]),
        ];

        if ($col['codigo'] === null || $col['descricao'] === null) {
            $this->command->error(
                'Não consegui identificar as colunas de código/descrição no CSV. ' .
                'Cabeçalho encontrado: ' . implode(' | ', $header)
            );
            fclose($handle);
            return;
        }

        if ($col['reducao_ibs'] === null || $col['reducao_cbs'] === null) {
            $this->command->warn(
                'Não achei as colunas de percentual de redução no CSV - vão ficar NULL. ' .
                'Cabeçalho encontrado: ' . implode(' | ', $header)
            );
        }

        $rows = [];
        $now = now();

        while (($data = fgetcsv($handle, 0, $this->delimitador)) !== false) {
            $codigo = preg_replace('/\D/', '', $data[$col['codigo']] ?? '');
            if (strlen($codigo) !== 6) {
                continue;
            }

            $rows[] = [
                'codigo'                 => $codigo,
                'descricao'              => trim($data[$col['descricao']] ?? ''),
                'cst_codigo'             => $col['cst_codigo'] !== null
                    ? preg_replace('/\D/', '', $data[$col['cst_codigo']] ?? '') ?: substr($codigo, 0, 3)
                    : substr($codigo, 0, 3),
                'cst_descricao'          => $col['cst_descricao'] !== null
                    ? trim($data[$col['cst_descricao']] ?? '')
                    : null,
                'percentual_reducao_ibs' => $col['reducao_ibs'] !== null
                    ? $this->parsePercentual($data[$col['reducao_ibs']] ?? null)
                    : null,
                'percentual_reducao_cbs' => $col['reducao_cbs'] !== null
                    ? $this->parsePercentual($data[$col['reducao_cbs']] ?? null)
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
                ['descricao', 'cst_codigo', 'cst_descricao', 'percentual_reducao_ibs', 'percentual_reducao_cbs', 'updated_at']
            );
        });

        $this->command->info(count($rows) . ' códigos de Classificação Tributária IBS/CBS importados (com percentuais de redução).');
    }

    /**
     * Converte "60,00%" / "60,00" / "0,6" / "60" pra um percentual numerico (60.00).
     * Ajuste aqui se o formato do CSV vier diferente do esperado.
     */
    private function parsePercentual(?string $valor): ?float
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        $limpo = str_replace(['%', ' '], '', trim($valor));
        $limpo = str_replace(',', '.', $limpo);

        if (!is_numeric($limpo)) {
            return null;
        }

        return (float) $limpo;
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