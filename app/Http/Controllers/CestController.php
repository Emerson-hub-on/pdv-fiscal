<?php

namespace App\Http\Controllers;

use App\Models\Cest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CestController extends Controller
{
    /**
     * GET /cest/listar?q=termo
     * Lista/busca CEST por código ou descrição — usado pelo modal.
     */
    public function listar(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('q', ''));

        $query = Cest::query();

        if ($termo !== '') {
            $termoNumerico = preg_replace('/\D/', '', $termo);
            $query->where(function ($q) use ($termo, $termoNumerico) {
                if ($termoNumerico !== '') {
                    $q->orWhere('codigo', 'like', "{$termoNumerico}%");
                }
                $q->orWhere('descricao', 'like', "%{$termo}%");
            });
        }

        $items = $query->orderBy('codigo')->limit(50)->get(['id', 'codigo', 'descricao']);

        return response()->json($items);
    }

    /**
     * POST /cest/criar
     * Cadastro manual de um CEST (ex: código novo do Convênio que ainda não veio no seed).
     */
    public function criar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'codigo'    => ['required', 'digits:7', 'unique:cests,codigo'],
            'descricao' => ['required', 'string'],
        ]);

        $cest = Cest::create([
            'codigo'          => $data['codigo'],
            'descricao'       => $data['descricao'],
            'segmento_codigo' => substr($data['codigo'], 0, 2),
        ]);

        return response()->json($cest);
    }

    /**
     * POST /cest/editar
     */
    public function editar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id'        => ['required', 'exists:cests,id'],
            'codigo'    => ['required', 'digits:7', 'unique:cests,codigo,' . $request->input('id')],
            'descricao' => ['required', 'string'],
        ]);

        $cest = Cest::findOrFail($data['id']);
        $cest->update([
            'codigo'          => $data['codigo'],
            'descricao'       => $data['descricao'],
            'segmento_codigo' => substr($data['codigo'], 0, 2),
        ]);

        return response()->json($cest);
    }

    /**
     * POST /cest/excluir
     */
    public function excluir(Request $request): JsonResponse
    {
        $cest = Cest::find($request->input('id'));

        if (! $cest) {
            return response()->json(['erro' => 'CEST não encontrado.']);
        }

        if ($cest->produtos()->exists()) {
            return response()->json(['erro' => 'Não é possível excluir: existem produtos usando este CEST.']);
        }

        $cest->delete();

        return response()->json(['sucesso' => true]);
    }
}