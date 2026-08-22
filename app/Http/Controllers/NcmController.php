<?php

namespace App\Http\Controllers;

use App\Models\Ncm;
use Illuminate\Http\Request;

class NcmController extends Controller
{
    public function listar(Request $request)
    {
        $termo = trim($request->get('q', ''));

        $ncms = Ncm::when($termo, function ($q) use ($termo) {
                $q->where('codigo', 'like', "%{$termo}%")
                  ->orWhere('descricao', 'like', "%{$termo}%");
            })
            ->orderByRaw("
                CASE
                    WHEN codigo = ? THEN 0
                    WHEN codigo LIKE ? THEN 1
                    WHEN descricao LIKE ? THEN 2
                    ELSE 3
                END
            ", [$termo, "{$termo}%", "{$termo}%"])
            ->orderBy('codigo')
            ->limit(50)
            ->get(['id', 'codigo', 'descricao', 'cadastro_avulso']);

        return response()->json($ncms);
    }

    public function criar(Request $request)
    {
        $validado = $request->validate([
            'codigo'    => 'required|string|size:8',
            'descricao' => 'required|string|max:255',
        ]);

        $ncm = Ncm::firstOrCreate(
            ['codigo' => $validado['codigo']],
            ['descricao' => $validado['descricao'], 'cadastro_avulso' => true]
        );

        return response()->json($ncm);
    }

    public function editar(Request $request)
    {
        $validado = $request->validate([
            'id'        => 'required|exists:ncms,id',
            'codigo'    => 'required|string|size:8',
            'descricao' => 'required|string|max:255',
        ]);

        $ncm = Ncm::findOrFail($validado['id']);
        $ncm->update([
            'codigo'    => $validado['codigo'],
            'descricao' => $validado['descricao'],
        ]);

        return response()->json($ncm);
    }

    public function excluir(Request $request)
    {
        $id = $request->get('id');
        $ncm = Ncm::findOrFail($id);

        $emUso = \App\Models\Produto::where('ncm_id', $id)->exists();

        if ($emUso) {
            return response()->json([
                'erro' => 'Não é possível excluir: existem produtos usando este NCM.'
            ], 422);
        }

        $ncm->delete();

        return response()->json(['sucesso' => true]);
    }
}