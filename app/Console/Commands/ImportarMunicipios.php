<?php

namespace App\Console\Commands;

use App\Models\Mdfe;
use App\Models\Municipio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportarMunicipios extends Command
{
    protected $signature = 'municipios:importar {--arquivo= : Caminho de um JSON do IBGE já baixado (se o servidor não tiver acesso à internet)}';

    protected $description = 'Importa/atualiza a tabela de municípios (código IBGE, nome e UF) a partir da API de localidades do IBGE';

    private const URL = 'https://servicodados.ibge.gov.br/api/v1/localidades/municipios';

    public function handle(): int
    {
        try {
            $dados = $this->option('arquivo')
                ? json_decode(file_get_contents($this->option('arquivo')), true)
                : Http::timeout(60)->retry(2, 1000)->get(self::URL)->throw()->json();
        } catch (\Throwable $e) {
            $this->error('Não foi possível obter a lista do IBGE: ' . $e->getMessage());

            return self::FAILURE;
        }

        $agora  = now();
        $linhas = [];
        $ignorados = 0;

        foreach ((array) $dados as $m) {
            $codigo = (string) ($m['id'] ?? '');
            $nome   = trim((string) ($m['nome'] ?? ''));
            // A UF vem dos 2 primeiros dígitos do código: não depende da estrutura (micro/mesorregião) do JSON
            $uf     = strlen($codigo) === 7 ? Mdfe::ufDoMunicipio($codigo) : null;

            if (!$uf || $nome === '') {
                $ignorados++;
                continue;
            }

            $linhas[] = [
                'codigo_ibge' => $codigo,
                'nome'        => $nome,
                'nome_busca'  => Municipio::normalizar($nome),
                'uf'          => $uf,
                'created_at'  => $agora,
                'updated_at'  => $agora,
            ];
        }

        // O Brasil tem 5.570 municípios; bem menos que isso indica resposta incompleta
        if (count($linhas) < 5000) {
            $this->error('Lista incompleta (' . count($linhas) . ' municípios válidos). Nada foi gravado.');

            return self::FAILURE;
        }

        foreach (array_chunk($linhas, 500) as $lote) {
            Municipio::upsert($lote, ['codigo_ibge'], ['nome', 'nome_busca', 'uf', 'updated_at']);
        }

        $this->info(count($linhas) . ' municípios importados/atualizados' . ($ignorados ? " ({$ignorados} ignorados)." : '.'));

        return self::SUCCESS;
    }
}
