<?php

namespace App\Http\Controllers;

use App\Models\Condutor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CondutorController extends Controller
{
    public function index(Request $request)
    {
        $filtro     = $request->get('status', 'ativos');   // ativos | inativos | todos
        $ordenarPor = $request->get('ordenar', 'nome');    // nome | cpf
        $busca      = trim((string) $request->get('busca', ''));
        $cpf        = Condutor::normalizarCpf($busca);

        $condutores = Condutor::query()
            ->when($filtro === 'ativos', fn ($q) => $q->where('ativo', true))
            ->when($filtro === 'inativos', fn ($q) => $q->where('ativo', false))
            ->when($busca !== '', function ($q) use ($busca, $cpf) {
                $q->where(function ($q) use ($busca, $cpf) {
                    $q->where('nome', 'like', "%{$busca}%");
                    if ($cpf !== '') {
                        $q->orWhere('cpf', 'like', "%{$cpf}%");
                    }
                });
            })
            ->orderBy($ordenarPor === 'cpf' ? 'cpf' : 'nome')
            ->paginate(15)
            ->withQueryString();

        // A busca ao vivo pede só a tabela
        if ($request->ajax()) {
            return view('condutores._tabela', compact('condutores'));
        }

        return view('condutores.index', compact('condutores', 'filtro', 'ordenarPor'));
    }

    /** Busca JSON para o seletor de condutor no MDF-e. */
    public function listar(Request $request)
    {
        $termo = trim((string) $request->get('termo', ''));
        $cpf   = Condutor::normalizarCpf($termo);

        $condutores = Condutor::ativos()
            ->when($termo !== '', function ($q) use ($termo, $cpf) {
                $q->where(function ($q) use ($termo, $cpf) {
                    $q->where('nome', 'like', "%{$termo}%");
                    if ($cpf !== '') {
                        $q->orWhere('cpf', 'like', "%{$cpf}%");
                    }
                });
            })
            ->orderBy('nome')
            ->limit(20)
            ->get();

        return response()->json($condutores);
    }

    public function create()
    {
        return view('condutores.create');
    }

    public function store(Request $request)
    {
        Condutor::create($this->validarDados($request));

        return redirect()->route('condutores.index')->with('sucesso', 'Condutor cadastrado com sucesso.');
    }

    public function edit(Condutor $condutor)
    {
        return view('condutores.edit', compact('condutor'));
    }

    public function update(Request $request, Condutor $condutor)
    {
        // "ativo" não é tocado aqui: quem muda é o botão Inativar/Reativar da listagem
        $condutor->update($this->validarDados($request, $condutor->id));

        return redirect()->route('condutores.index')->with('sucesso', 'Condutor atualizado com sucesso.');
    }

    public function toggleAtivo(Condutor $condutor)
    {
        $condutor->update(['ativo' => !$condutor->ativo]);

        return back()->with('sucesso', $condutor->ativo ? 'Condutor reativado.' : 'Condutor inativado.');
    }

    private function validarDados(Request $request, ?int $ignorarId = null): array
    {
        $request->merge([
            'cpf'  => Condutor::normalizarCpf($request->input('cpf')),
            'nome' => trim((string) $request->input('nome')),
        ]);

        return $request->validate([
            'nome' => ['required', 'string', 'min:2', 'max:60'],
            'cpf'  => [
                'required',
                Rule::unique('condutores', 'cpf')->ignore($ignorarId),
                fn ($attr, $valor, $fail) => Condutor::cpfValido($valor) || $fail('CPF inválido.'),
            ],
        ], [
            'cpf.unique' => 'Já existe um condutor com esse CPF.',
        ]);
    }
}
