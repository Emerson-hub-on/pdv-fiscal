<?php

namespace App\Http\Controllers;

use App\Models\ClassificacaoPisCofins;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassificacaoPisCofinsController extends Controller
{
    /**
     * GET /pis-cofins/listar?q=termo
     */
    public function listar(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('q', ''));

        $query = ClassificacaoPisCofins::query();

        if ($termo !== '') {
            $query->where(function ($q) use ($termo) {
                $q->where('codigo', 'like', "{$termo}%")
                  ->orWhere('descricao', 'like', "%{$termo}%");
            });
        }

        $items = $query->orderBy('codigo')->limit(50)
            ->get(['id', 'codigo', 'descricao', 'aliquota_pis', 'aliquota_cofins']);

        return response()->json($items);
    }

    /**
     * POST /pis-cofins/criar
     */
    public function criar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'codigo'          => ['required', 'digits:2'],
            'descricao'       => ['required', 'string'],
            'aliquota_pis'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'aliquota_cofins' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $item = ClassificacaoPisCofins::create($data);

        return response()->json($item);
    }

    /**
     * POST /pis-cofins/editar
     */
    public function editar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id'              => ['required', 'exists:classificacoes_pis_cofins,id'],
            'codigo'          => ['required', 'digits:2'],
            'descricao'       => ['required', 'string'],
            'aliquota_pis'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'aliquota_cofins' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $item = ClassificacaoPisCofins::findOrFail($data['id']);
        $item->update($data);

        return response()->json($item);
    }

    /**
     * POST /pis-cofins/excluir
     */
    public function excluir(Request $request): JsonResponse
    {
        $item = ClassificacaoPisCofins::find($request->input('id'));

        if (! $item) {
            return response()->json(['erro' => 'Classificação não encontrada.']);
        }

        if ($item->produtos()->exists()) {
            return response()->json(['erro' => 'Não é possível excluir: existem produtos usando esta classificação.']);
        }

        $item->delete();

        return response()->json(['sucesso' => true]);
    }
}