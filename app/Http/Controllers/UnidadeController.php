<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use App\Models\ProdutoVariante;
use App\Models\Unidade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * As unidades só são mantidas pelo modal do cadastro de produto: não há telas próprias,
 * por isso tudo aqui responde em JSON. Sem exclusão — a unidade em uso nunca some.
 */
class UnidadeController extends Controller
{
    private const MSG_ESTOQUE_FRACIONADO = 'Existem produtos com esta unidade e estoque fracionado. Ajuste o estoque deles para valores inteiros antes de desmarcar.';

    /** Nova unidade, cadastrada pelo modal do produto. */
    public function store(Request $request): JsonResponse
    {
        $request->merge(['sigla' => strtoupper(trim((string) $request->input('sigla')))]);

        $dados = $request->validate([
            'sigla'     => ['required', 'string', 'max:6', 'regex:/^[A-Z0-9]+$/', Rule::unique('unidades', 'sigla')],
            'descricao' => 'required|string|max:60',
        ], [
            'sigla.regex'  => 'A sigla deve ter apenas letras e números, sem espaços.',
            'sigla.unique' => 'Já existe uma unidade com esta sigla.',
        ]);

        $unidade = Unidade::create($dados + [
            'permite_fracionado' => $request->boolean('permite_fracionado'),
            'ativo' => true,
        ]);

        return response()->json([
            'id' => $unidade->id,
            'sigla' => $unidade->sigla,
            'descricao' => $unidade->descricao,
            'permite_fracionado' => $unidade->permite_fracionado,
        ], 201);
    }

    /** Toggle "fracionada" do modal. A sigla nunca muda: ela é gravada nos produtos e vai no XML da nota. */
    public function atualizarFracionado(Request $request, Unidade $unidade): JsonResponse
    {
        $fracionado = $request->boolean('permite_fracionado');

        // Desmarcar não pode deixar produto com estoque quebrado (ex.: 2,5)
        if ($unidade->permite_fracionado && ! $fracionado && $this->temEstoqueFracionado($unidade->sigla)) {
            return response()->json(['message' => self::MSG_ESTOQUE_FRACIONADO], 422);
        }

        $unidade->update(['permite_fracionado' => $fracionado]);

        return response()->json([
            'id' => $unidade->id,
            'permite_fracionado' => $unidade->permite_fracionado,
        ]);
    }

    private function temEstoqueFracionado(string $sigla): bool
    {
        $fracionado = fn ($q) => $q->whereRaw('estoque <> FLOOR(estoque)')
            ->orWhereRaw('estoque_minimo <> FLOOR(estoque_minimo)');

        return Produto::where('unidade_comercial', $sigla)->where($fracionado)->exists()
            || ProdutoVariante::whereHas('produto', fn ($p) => $p->where('unidade_comercial', $sigla))
                ->where($fracionado)->exists();
    }
}