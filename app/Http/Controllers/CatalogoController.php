<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Grupo;
use Illuminate\Http\Request;

class CatalogoController extends Controller
{
    public function listar(Request $request)
    {
        $tipo = $request->get('tipo'); // categoria, marca, grupo
        $termo = $request->get('q', '');

        $model = match($tipo) {
            'categoria' => Categoria::class,
            'marca' => Marca::class,
            'grupo' => Grupo::class,
            default => abort(422),
        };

        $items = $model::when($termo, fn($q) => $q->where('nome', 'like', "%{$termo}%"))
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return response()->json($items);
    }

    public function criar(Request $request)
    {
        $tipo = $request->get('tipo');
        $validado = $request->validate(['nome' => 'required|string|max:100']);

        $item = match($tipo) {
            'categoria' => Categoria::firstOrCreate(['nome' => $validado['nome']]),
            'marca' => Marca::firstOrCreate(['nome' => $validado['nome']]),
            'grupo' => Grupo::firstOrCreate(['nome' => $validado['nome']]),
            default => abort(422),
        };

        return response()->json($item);
    }
}