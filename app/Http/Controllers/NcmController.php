<?php

namespace App\Http\Controllers;

use App\Models\Ncm;
use Illuminate\Http\Request;

class NcmController extends Controller
{
    public function listar(Request $request)
    {
        $termo = $request->get('q', '');

        $ncms = Ncm::when($termo, function ($q) use ($termo) {
            $q->where('codigo', 'like', "%{$termo}%")
              ->orWhere('descricao', 'like', "%{$termo}%");
        })
        ->orderBy('codigo')
        ->limit(30) // limita pra nao travar com os 10000 registros
        ->get(['id', 'codigo', 'descricao', 'cadastro_avulso']);

        return response()->json($ncms);
    }

    public function criar(Request $request)
    {
        $validado = $request->validate([
            'codigo' => 'required|string|size:8',
            'descricao' => 'required|string|max:255',
        ]);

        $ncm = Ncm::firstOrCreate(
            ['codigo' => $validado['codigo']],
            ['descricao' => $validado['descricao'], 'cadastro_avulso' => true]
        );

        return response()->json($ncm);
    }
}