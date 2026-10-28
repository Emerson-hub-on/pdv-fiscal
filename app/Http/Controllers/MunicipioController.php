<?php

namespace App\Http\Controllers;

use App\Models\Municipio;
use Illuminate\Http\Request;

class MunicipioController extends Controller
{
    /**
     * Busca para o autocompletar de município. A UF informada não filtra: só põe os municípios dela na frente
     * (assim um município digitado com a UF errada ainda é encontrado).
     */
    public function buscar(Request $request)
    {
        $termo = Municipio::normalizar((string) $request->get('termo', ''));

        if (mb_strlen($termo) < 2) {
            return response()->json([]);
        }

        $uf     = strtoupper(trim((string) $request->get('uf', '')));
        $seguro = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $termo);

        $municipios = Municipio::query()
            ->where('nome_busca', 'like', "%{$seguro}%")
            ->orderByRaw('nome_busca LIKE ? DESC', ["{$seguro}%"])
            ->when($uf !== '', fn ($q) => $q->orderByRaw('uf = ? DESC', [$uf]))
            ->orderBy('nome')
            ->limit(15)
            ->get(['codigo_ibge', 'nome', 'uf']);

        return response()->json($municipios);
    }
}
