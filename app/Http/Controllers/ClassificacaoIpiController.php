<?php

namespace App\Http\Controllers;

use App\Models\ClassificacaoIpi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassificacaoIpiController extends Controller
{
    /**
     * GET /ipi/listar?q=termo
     */
    public function listar(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('q', ''));

        $query = ClassificacaoIpi::query();

        if ($termo !== '') {
            $query->where(function ($q) use ($termo) {
                $q->where('codigo', 'like', "{$termo}%")
                  ->orWhere('descricao', 'like', "%{$termo}%")
                  ->orWhere('cenq', 'like', "{$termo}%");
            });
        }

        $items = $query->orderBy('codigo')->limit(50)
            ->get(['id', 'codigo', 'descricao', 'cenq', 'aliquota']);

        return response()->json($items);
    }

    /**
     * POST /ipi/criar
     */
    public function criar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'codigo'    => ['required', 'digits:2'],
            'descricao' => ['required', 'string'],
            'cenq'      => ['required', 'digits:3'],
            'aliquota'  => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $item = ClassificacaoIpi::create($data);

        return response()->json($item);
    }

    /**
     * POST /ipi/editar
     */
    public function editar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id'        => ['required', 'exists:classificacoes_ipi,id'],
            'codigo'    => ['required', 'digits:2'],
            'descricao' => ['required', 'string'],
            'cenq'      => ['required', 'digits:3'],
            'aliquota'  => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $item = ClassificacaoIpi::findOrFail($data['id']);
        $item->update($data);

        return response()->json($item);
    }

    /**
     * POST /ipi/excluir
     */
    public function excluir(Request $request): JsonResponse
    {
        $item = ClassificacaoIpi::find($request->input('id'));

        if (! $item) {
            return response()->json(['erro' => 'Classificação IPI não encontrada.']);
        }

        if ($item->produtos()->exists()) {
            return response()->json(['erro' => 'Não é possível excluir: existem produtos usando esta classificação.']);
        }

        $item->delete();

        return response()->json(['sucesso' => true]);
    }
}