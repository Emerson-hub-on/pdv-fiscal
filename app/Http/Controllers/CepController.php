<?php

namespace App\Http\Controllers;

use App\Models\Municipio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CepController extends Controller
{
    /**
     * Consulta um CEP no ViaCEP (servidor para servidor) e devolve o endereço no formato dos nossos cadastros.
     * O código IBGE vem do ViaCEP; se faltar, é buscado na tabela de municípios pelo nome e UF.
     */
    public function buscar(Request $request)
    {
        $cep = preg_replace('/\D/', '', (string) $request->get('cep'));

        if (strlen($cep) !== 8) {
            return response()->json(['erro' => 'CEP inválido.'], 422);
        }

        $chave = "cep:{$cep}";

        if ($emCache = Cache::get($chave)) {
            return response()->json($emCache);
        }

        try {
            $resposta = Http::timeout(8)->retry(1, 300)->get("https://viacep.com.br/ws/{$cep}/json/");
        } catch (\Throwable $e) {
            return response()->json(['erro' => 'Não foi possível consultar o CEP agora. Preencha o endereço manualmente.'], 503);
        }

        if ($resposta->failed()) {
            // O ViaCEP responde 400 para CEP malformado; qualquer outra falha é indisponibilidade
            $naoEncontrado = $resposta->status() === 400;

            return response()->json([
                'erro' => $naoEncontrado ? 'CEP não encontrado.' : 'Não foi possível consultar o CEP agora. Preencha o endereço manualmente.',
            ], $naoEncontrado ? 404 : 503);
        }

        $json = $resposta->json();

        if (!is_array($json) || !empty($json['erro'])) {
            return response()->json(['erro' => 'CEP não encontrado.'], 404);
        }

        $nome = trim((string) ($json['localidade'] ?? ''));
        $uf   = strtoupper(trim((string) ($json['uf'] ?? '')));
        $ibge = preg_match('/^\d{7}$/', (string) ($json['ibge'] ?? '')) ? $json['ibge'] : null;

        // Sem código no retorno: procura na nossa tabela pelo nome (sem acento) e UF
        $ibge ??= Municipio::where('uf', $uf)
            ->where('nome_busca', Municipio::normalizar($nome))
            ->value('codigo_ibge');

        // Usa o nome oficial da nossa tabela, para ficar igual ao que o autocompletar de município grava
        if ($ibge && ($oficial = Municipio::where('codigo_ibge', $ibge)->value('nome'))) {
            $nome = $oficial;
        }

        $dados = [
            'cep'           => $cep,
            'logradouro'    => trim((string) ($json['logradouro'] ?? '')),
            'bairro'        => trim((string) ($json['bairro'] ?? '')),
            'municipio'     => $nome,
            'uf'            => $uf,
            'cod_municipio' => $ibge,
        ];

        Cache::put($chave, $dados, now()->addDays(30));

        return response()->json($dados);
    }
}
