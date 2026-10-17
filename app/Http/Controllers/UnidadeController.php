<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use App\Models\ProdutoVariante;
use App\Models\Unidade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UnidadeController extends Controller
{
    private const MSG_ESTOQUE_FRACIONADO = 'Existem produtos com esta unidade e estoque fracionado. Ajuste o estoque deles para valores inteiros antes de desmarcar.';

    public function index(Request $request)
    {
        $unidades = Unidade::query()
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = $request->busca;
                $q->where(fn ($w) => $w->where('sigla', 'like', "%{$busca}%")
                    ->orWhere('descricao', 'like', "%{$busca}%"));
            })
            ->orderBy('sigla')
            ->paginate(20)
            ->withQueryString();

        return view('unidades.index', compact('unidades'));
    }

    public function create()
    {
        return view('unidades.create', ['unidade' => new Unidade(['permite_fracionado' => false, 'ativo' => true])]);
    }

    public function store(Request $request)
    {
        $request->merge(['sigla' => strtoupper(trim((string) $request->input('sigla')))]);

        $dados = $request->validate([
            'sigla'     => ['required', 'string', 'max:6', 'regex:/^[A-Z0-9]+$/', Rule::unique('unidades', 'sigla')],
            'descricao' => 'required|string|max:60',
        ], [
            'sigla.regex'  => 'A sigla deve ter apenas letras e números, sem espaços.',
            'sigla.unique' => 'Já existe uma unidade com esta sigla.',
        ]);

        Unidade::create($dados + [
            'permite_fracionado' => $request->boolean('permite_fracionado'),
            'ativo' => true,
        ]);

        return redirect()->route('unidades.index')->with('sucesso', 'Unidade cadastrada com sucesso.');
    }

    public function edit(Unidade $unidade)
    {
        return view('unidades.edit', compact('unidade'));
    }

    /** A sigla não muda: ela é gravada nos produtos e vai no XML da nota. */
    public function update(Request $request, Unidade $unidade)
    {
        $dados = $request->validate(['descricao' => 'required|string|max:60']);
        $fracionado = $request->boolean('permite_fracionado');

        // Desmarcar "fracionado" não pode deixar produto com estoque quebrado (ex.: 2,5)
        if ($unidade->permite_fracionado && ! $fracionado && $this->temEstoqueFracionado($unidade->sigla)) {
            throw ValidationException::withMessages([
                'permite_fracionado' => self::MSG_ESTOQUE_FRACIONADO,
            ]);
        }

        $unidade->update($dados + ['permite_fracionado' => $fracionado]);

        return redirect()->route('unidades.index')->with('sucesso', 'Unidade atualizada com sucesso.');
    }

    /** Usado pelo modal de unidades do cadastro de produto (toggle "fracionada"). */
    public function atualizarFracionado(Request $request, Unidade $unidade): JsonResponse
    {
        $fracionado = $request->boolean('permite_fracionado');

        if ($unidade->permite_fracionado && ! $fracionado && $this->temEstoqueFracionado($unidade->sigla)) {
            return response()->json(['message' => self::MSG_ESTOQUE_FRACIONADO], 422);
        }

        $unidade->update(['permite_fracionado' => $fracionado]);

        return response()->json([
            'id' => $unidade->id,
            'permite_fracionado' => $unidade->permite_fracionado,
        ]);
    }

    /** Sem exclusão: unidade em uso nunca some, só deixa de aparecer para novos cadastros. */
    public function toggleAtivo(Unidade $unidade)
    {
        $unidade->update(['ativo' => ! $unidade->ativo]);

        return redirect()->route('unidades.index')
            ->with('sucesso', $unidade->ativo ? 'Unidade reativada.' : 'Unidade inativada.');
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