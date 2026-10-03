<?php

namespace App\Http\Controllers;

use App\Models\Transportador;
use App\Models\Veiculo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VeiculoController extends Controller
{
    public function index(Request $request)
    {
        $filtro     = $request->get('status', 'ativos');   // ativos | inativos | todos
        $ordenarPor = $request->get('ordenar', 'placa');   // placa | transportadora
        $busca      = trim((string) $request->get('busca', ''));
        $placa      = Veiculo::normalizarPlaca($busca);

        $veiculos = Veiculo::query()
            ->leftJoin('transportadores', 'transportadores.id', '=', 'veiculos.transportador_id')
            ->select('veiculos.*')
            ->with('transportador')
            ->when($filtro === 'ativos', fn ($q) => $q->where('veiculos.ativo', true))
            ->when($filtro === 'inativos', fn ($q) => $q->where('veiculos.ativo', false))
            ->when($busca !== '', function ($q) use ($busca, $placa) {
                $q->where(function ($q) use ($busca, $placa) {
                    $q->where('transportadores.nome', 'like', "%{$busca}%");
                    if ($placa !== '') {
                        $q->orWhere('veiculos.placa', 'like', "%{$placa}%");
                    }
                });
            })
            ->when(
                $ordenarPor === 'transportadora',
                fn ($q) => $q->orderBy('transportadores.nome')->orderBy('veiculos.placa'),
                fn ($q) => $q->orderBy('veiculos.placa')
            )
            ->paginate(15)
            ->withQueryString();

        if ($request->ajax()) {
            return view('veiculos._tabela', compact('veiculos'));
        }

        return view('veiculos.index', compact('veiculos', 'filtro', 'ordenarPor'));
    }

    public function create()
    {
        return view('veiculos.create', ['transportadores' => $this->transportadoresParaSelect()]);
    }

    public function store(Request $request)
    {
        Veiculo::create($this->validarDados($request));

        return redirect()->route('veiculos.index')->with('sucesso', 'Veículo cadastrado com sucesso.');
    }

    public function edit(Veiculo $veiculo)
    {
        return view('veiculos.edit', [
            'veiculo'         => $veiculo,
            'transportadores' => $this->transportadoresParaSelect($veiculo->transportador_id),
        ]);
    }

    public function update(Request $request, Veiculo $veiculo)
    {
        $veiculo->update($this->validarDados($request, $veiculo->id));

        return redirect()->route('veiculos.index')->with('sucesso', 'Veículo atualizado com sucesso.');
    }

    public function toggleAtivo(Veiculo $veiculo)
    {
        $veiculo->update(['ativo' => !$veiculo->ativo]);

        return back()->with('sucesso', $veiculo->ativo ? 'Veículo reativado.' : 'Veículo inativado.');
    }

    /** Transportadoras ativas (mais a atual do veículo, mesmo que tenha sido inativada depois). */
    private function transportadoresParaSelect(?int $incluirId = null)
    {
        return Transportador::query()
            ->where(fn ($q) => $q->where('ativo', true)->when($incluirId, fn ($q) => $q->orWhere('id', $incluirId)))
            ->orderBy('nome')
            ->get(['id', 'nome']);
    }

    private function validarDados(Request $request, ?int $ignorarId = null): array
    {
        $request->merge([
            'placa'            => Veiculo::normalizarPlaca($request->input('placa')),
            'uf'               => strtoupper(trim((string) $request->input('uf'))),
            'rntrc'            => preg_replace('/\D/', '', (string) $request->input('rntrc')) ?: null,
            'transportador_id' => $request->input('transportador_id') ?: null,
        ]);

        return $request->validate([
            'placa' => [
                'required',
                'regex:/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/',
                Rule::unique('veiculos', 'placa')->ignore($ignorarId),
            ],
            'uf'               => ['required', Rule::in(Transportador::UFS)],
            'rntrc'            => ['nullable', 'regex:/^\d{8}$/'],
            'transportador_id' => ['nullable', 'exists:transportadores,id'],
        ], [
            'placa.regex'  => 'Placa inválida. Use o formato ABC1234 ou ABC1D23 (Mercosul).',
            'placa.unique' => 'Já existe um veículo cadastrado com essa placa.',
            'rntrc.regex'  => 'O RNTRC deve ter 8 dígitos.',
        ]);
    }
}