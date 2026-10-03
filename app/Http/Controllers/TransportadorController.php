<?php

namespace App\Http\Controllers;

use App\Models\Transportador;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransportadorController extends Controller
{
    public function listar(Request $request)
    {
        $termo = trim((string) $request->get('termo', ''));
        $doc   = Transportador::normalizarDocumento($termo);

        $transportadores = Transportador::ativos()
            ->when($termo !== '', function ($q) use ($termo, $doc) {
                $q->where(function ($q) use ($termo, $doc) {
                    $q->where('nome', 'like', "%{$termo}%");

                    if ($doc !== '') {
                        $q->orWhere('documento', 'like', "%{$doc}%");
                    }
                });
            })
            ->orderBy('nome')
            ->limit(20)
            ->get();

        return response()->json($transportadores);
    }

    public function criar(Request $request)
    {
        $dados = $this->validarDados($request);

        return response()->json(Transportador::create($dados));
    }

    public function editar(Request $request)
    {
        $request->validate(['id' => ['required', 'exists:transportadores,id']]);

        $transportador = Transportador::findOrFail($request->id);
        $transportador->update($this->validarDados($request, $transportador->id));

        return response()->json($transportador->fresh());
    }

    private function validarDados(Request $request, ?int $ignorarId = null): array
    {
        $ie = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $request->input('ie')));

        $request->merge([
            'documento' => Transportador::normalizarDocumento($request->input('documento')),
            'uf'        => strtoupper(trim((string) $request->input('uf'))),
            'ie'        => $ie !== '' ? $ie : null,
            'numero'    => trim((string) $request->input('numero')) ?: 'S/N',
        ]);

        $dados = $request->validate([
            'documento' => [
                'required',
                Rule::unique('transportadores', 'documento')->ignore($ignorarId),
                fn ($attr, $valor, $fail) => Transportador::documentoValido($valor) || $fail('CPF/CNPJ inválido.'),
            ],
            'nome'       => ['required', 'string', 'min:2', 'max:60'],
            'ie'         => ['nullable', 'regex:/^(ISENTO|\d{2,14})$/'],
            'logradouro' => ['required', 'string', 'max:60'],
            'numero'     => ['required', 'string', 'max:10'],
            'bairro'     => ['required', 'string', 'max:40'],
            'municipio'  => ['required', 'string', 'max:60'],
            'uf'         => ['required', Rule::in(Transportador::UFS)],
        ], [
            'documento.unique' => 'Já existe um transportador com esse CPF/CNPJ.',
            'ie.regex'         => 'Informe a inscrição estadual só com números, ou ISENTO.',
        ]);

        // No XML o endereço vira um único campo (xEnder) de até 60 caracteres
        $endereco = "{$dados['logradouro']}, {$dados['numero']}, {$dados['bairro']}";
        if (mb_strlen($endereco) > 60) {
            throw ValidationException::withMessages([
                'logradouro' => 'Logradouro, número e bairro juntos passam de 60 caracteres (limite do XML da NF-e). Abrevie o logradouro ou o bairro.',
            ]);
        }

        $dados['tipo_pessoa'] = strlen($dados['documento']) === 11 ? 'F' : 'J';

        return $dados;
    }

    public function index(Request $request)
    {
        $filtro     = $request->get('status', 'ativos');   // ativos | inativos | todos
        $ordenarPor = $request->get('ordenar', 'nome');    // nome | documento
        $busca      = trim((string) $request->get('busca', ''));
        $doc        = Transportador::normalizarDocumento($busca);

        $transportadores = Transportador::query()
            ->when($filtro === 'ativos', fn ($q) => $q->where('ativo', true))
            ->when($filtro === 'inativos', fn ($q) => $q->where('ativo', false))
            ->when($busca !== '', function ($q) use ($busca, $doc) {
                $q->where(function ($q) use ($busca, $doc) {
                    $q->where('nome', 'like', "%{$busca}%");
                    if ($doc !== '') {
                        $q->orWhere('documento', 'like', "%{$doc}%");
                    }
                });
            })
            ->orderBy($ordenarPor === 'documento' ? 'documento' : 'nome')
            ->paginate(15)
            ->withQueryString();

        // A busca ao vivo pede só a tabela
        if ($request->ajax()) {
            return view('transportadores._tabela', compact('transportadores'));
        }

        return view('transportadores.index', compact('transportadores', 'filtro', 'ordenarPor'));
    }

    public function create()
    {
        return view('transportadores.create');
    }

    public function store(Request $request)
    {
        Transportador::create($this->validarDados($request));

        return redirect()->route('transportadores.index')->with('sucesso', 'Transportadora cadastrada com sucesso.');
    }

    public function edit(Transportador $transportador)
    {
        return view('transportadores.edit', compact('transportador'));
    }

    public function update(Request $request, Transportador $transportador)
    {
        // "ativo" não é tocado aqui: quem muda é o botão Inativar/Reativar da listagem
        $transportador->update($this->validarDados($request, $transportador->id));

        return redirect()->route('transportadores.index')->with('sucesso', 'Transportadora atualizada com sucesso.');
    }

    public function toggleAtivo(Transportador $transportador)
    {
        $transportador->update(['ativo' => !$transportador->ativo]);

        return back()->with('sucesso', $transportador->ativo ? 'Transportadora reativada.' : 'Transportadora inativada.');
    }
}