<?php

namespace App\Http\Controllers;

use App\Models\ClassificacaoTributaria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassificacaoTributariaController extends Controller
{
    /**
     * GET /classificacao-tributaria/listar?q=termo
     */
    public function listar(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('q', ''));

        $query = ClassificacaoTributaria::query();

        if ($termo !== '') {
            $termoNumerico = preg_replace('/\D/', '', $termo);
            $query->where(function ($q) use ($termo, $termoNumerico) {
                if ($termoNumerico !== '') {
                    $q->orWhere('codigo', 'like', "{$termoNumerico}%")
                      ->orWhere('cst_codigo', 'like', "{$termoNumerico}%");
                }
                $q->orWhere('descricao', 'like', "%{$termo}%")
                  ->orWhere('cst_descricao', 'like', "%{$termo}%");
            });
        }

        $items = $query->orderBy('codigo')->limit(50)
            ->get(['id', 'codigo', 'descricao', 'cst_codigo', 'cst_descricao']);

        return response()->json($items);
    }

    /**
     * POST /classificacao-tributaria/criar
     */
    public function criar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'codigo'        => ['required', 'digits:6', 'unique:classificacoes_tributarias,codigo'],
            'descricao'     => ['required', 'string'],
            'cst_codigo'    => ['required', 'digits:3'],
            'cst_descricao' => ['nullable', 'string'],
        ]);

        $item = ClassificacaoTributaria::create($data);

        return response()->json($item);
    }

    /**
     * POST /classificacao-tributaria/editar
     */
    public function editar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id'            => ['required', 'exists:classificacoes_tributarias,id'],
            'codigo'        => ['required', 'digits:6', 'unique:classificacoes_tributarias,codigo,' . $request->input('id')],
            'descricao'     => ['required', 'string'],
            'cst_codigo'    => ['required', 'digits:3'],
            'cst_descricao' => ['nullable', 'string'],
        ]);

        $item = ClassificacaoTributaria::findOrFail($data['id']);
        $item->update($data);

        return response()->json($item);
    }

    /**
     * POST /classificacao-tributaria/excluir
     */
    public function excluir(Request $request): JsonResponse
    {
        $item = ClassificacaoTributaria::find($request->input('id'));

        if (! $item) {
            return response()->json(['erro' => 'Classificação Tributária não encontrada.']);
        }

        if ($item->produtos()->exists()) {
            return response()->json(['erro' => 'Não é possível excluir: existem produtos usando esta classificação.']);
        }

        $item->delete();

        return response()->json(['sucesso' => true]);
    }
}