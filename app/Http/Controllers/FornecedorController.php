<?php

namespace App\Http\Controllers;

use App\Models\Fornecedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FornecedorController extends Controller
{
    public function index(Request $request)
    {
        $filtro = $request->get('status', 'ativos');
        $ordenarPor = $request->get('ordenar', 'nome'); // nome | cpf_cnpj

        $fornecedores = $this->consultarFornecedores($request, $filtro, $ordenarPor)
            ->paginate(20)
            ->withQueryString();

        if ($request->ajax()) {
            return view('fornecedores._tabela', compact('fornecedores'))->render();
        }

        return view('fornecedores.index', compact('fornecedores', 'filtro', 'ordenarPor'));
    }

    private function consultarFornecedores(Request $request, string $filtro, string $ordenarPor)
    {
        return Fornecedor::query()
            ->when($filtro === 'ativos', fn ($q) => $q->where('ativo', true))
            ->when($filtro === 'inativos', fn ($q) => $q->where('ativo', false))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $termo = $request->get('busca');
                $termoNumerico = preg_replace('/\D/', '', $termo);

                $q->where(function ($qq) use ($termo, $termoNumerico) {
                    $qq->where('nome', 'like', "%{$termo}%")
                       ->orWhere('nome_fantasia', 'like', "%{$termo}%");

                    if ($termoNumerico !== '') {
                        $qq->orWhere('cpf_cnpj', 'like', "{$termoNumerico}%");
                    }
                });
            })
            ->orderBy($ordenarPor === 'cpf_cnpj' ? 'cpf_cnpj' : 'nome');
    }

    public function create()
    {
        return view('fornecedores.create');
    }

    public function store(Request $request)
    {
        $validado = $this->validarFornecedor($request);

        Fornecedor::create($validado);

        return redirect()->route('fornecedores.index')
            ->with('sucesso', 'Fornecedor cadastrado com sucesso.');
    }

    public function edit(Fornecedor $fornecedor)
    {
        return view('fornecedores.edit', compact('fornecedor'));
    }

    public function update(Request $request, Fornecedor $fornecedor)
    {
        $validado = $this->validarFornecedor($request, $fornecedor->id);

        $fornecedor->update($validado);

        return redirect()->route('fornecedores.index')
            ->with('sucesso', 'Fornecedor atualizado com sucesso.');
    }

    public function toggleAtivo(Fornecedor $fornecedor)
    {
        $fornecedor->update(['ativo' => ! $fornecedor->ativo]);

        return redirect()->route('fornecedores.index')
            ->with('sucesso', $fornecedor->ativo ? 'Fornecedor reativado.' : 'Fornecedor inativado.');
    }

    /**
     * POST /fornecedores/criar-rapido
     * Cadastro rápido a partir do modal da entrada de nota (mínimo: tipo, nome e CPF/CNPJ).
     * O restante pode ser completado depois em Cadastros > Fornecedores.
     */
    public function criarRapido(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipo_pessoa' => ['required', 'in:fisica,juridica'],
            'nome'        => ['required', 'string', 'max:255'],
            'cpf_cnpj'    => ['required', 'string', 'unique:fornecedores,cpf_cnpj'],
        ], [
            'cpf_cnpj.unique' => 'Já existe um fornecedor com este CPF/CNPJ.',
        ]);

        $data['cpf_cnpj'] = preg_replace('/\D/', '', $data['cpf_cnpj']);

        $tamanhoEsperado = $data['tipo_pessoa'] === 'fisica' ? 11 : 14;
        if (strlen($data['cpf_cnpj']) !== $tamanhoEsperado) {
            return response()->json([
                'errors' => ['cpf_cnpj' => ['Documento inválido para ' . ($data['tipo_pessoa'] === 'fisica' ? 'CPF (11 dígitos)' : 'CNPJ (14 dígitos)')]],
            ], 422);
        }

        // Duplicidade depois de limpar a máscara (a regra unique acima vê o texto digitado)
        if (Fornecedor::where('cpf_cnpj', $data['cpf_cnpj'])->exists()) {
            return response()->json([
                'errors' => ['cpf_cnpj' => ['Já existe um fornecedor com este CPF/CNPJ.']],
            ], 422);
        }

        $fornecedor = Fornecedor::create($data);

        return response()->json([
            'id'   => $fornecedor->id,
            'nome' => $fornecedor->nome_exibicao . ' — ' . $fornecedor->cpf_cnpj_formatado,
        ]);
    }

    private function validarFornecedor(Request $request, $idAtual = null): array
    {
        $tipoPessoa = $request->input('tipo_pessoa');

        // O CEP é validado sem máscara (size:8)
        if ($request->filled('cep')) {
            $request->merge(['cep' => preg_replace('/\D/', '', $request->input('cep'))]);
        }

        $validado = $request->validate([
            'tipo_pessoa'   => 'required|in:fisica,juridica',
            'nome'          => 'required|string|max:255',
            'nome_fantasia' => 'nullable|string|max:255',
            'cpf_cnpj'      => 'required|string',
            'indicador_ie'  => 'required|in:contribuinte,isento,nao_contribuinte',
            'ie'            => 'required_if:indicador_ie,contribuinte|nullable|string|max:20',
            'email'         => 'nullable|email|max:255',
            'telefone'      => 'nullable|string|max:20',
            'cep'           => 'nullable|string|size:8',
            'logradouro'    => 'nullable|string|max:255',
            'numero'        => 'nullable|string|max:20',
            'complemento'   => 'nullable|string|max:100',
            'bairro'        => 'nullable|string|max:100',
            'municipio'     => 'nullable|string|max:100',
            'cod_municipio' => 'nullable|string|size:7',
            'uf'            => 'nullable|string|size:2',
        ]);

        $validado['cpf_cnpj'] = preg_replace('/\D/', '', $validado['cpf_cnpj']);

        $tamanhoEsperado = $tipoPessoa === 'fisica' ? 11 : 14;
        if (strlen($validado['cpf_cnpj']) !== $tamanhoEsperado) {
            abort(422, 'Documento inválido para ' . ($tipoPessoa === 'fisica' ? 'CPF (11 dígitos)' : 'CNPJ (14 dígitos)'));
        }

        // Unicidade conferida já sem máscara (mesmo valor que vai para o banco)
        $duplicado = Fornecedor::where('cpf_cnpj', $validado['cpf_cnpj'])
            ->when($idAtual, fn ($q) => $q->where('id', '!=', $idAtual))
            ->exists();

        if ($duplicado) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'cpf_cnpj' => 'Já existe um fornecedor com este CPF/CNPJ.',
            ]);
        }

        // Pessoa física nunca tem IE
        if ($tipoPessoa === 'fisica') {
            $validado['indicador_ie'] = 'nao_contribuinte';
            $validado['ie'] = null;
        }

        return $validado;
    }
}