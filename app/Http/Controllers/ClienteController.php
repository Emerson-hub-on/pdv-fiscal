<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $filtro = $request->get('status', 'ativos');
        $ordenarPor = $request->get('ordenar', 'nome'); // nome | cpf_cnpj

        $clientes = $this->consultarClientes($request, $filtro, $ordenarPor)
            ->paginate(20)
            ->withQueryString();

        if ($request->ajax()) {
            return view('clientes._tabela', compact('clientes'))->render();
        }

        return view('clientes.index', compact('clientes', 'filtro', 'ordenarPor'));
    }

    private function consultarClientes(Request $request, string $filtro, string $ordenarPor)
    {
        return Cliente::query()
            ->when($filtro === 'ativos', fn ($q) => $q->where('ativo', true))
            ->when($filtro === 'inativos', fn ($q) => $q->where('ativo', false))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $termo = $request->get('busca');
                $termoNumerico = preg_replace('/\D/', '', $termo);
                $q->where(function ($qq) use ($termo, $termoNumerico) {
                    $qq->where('nome', 'like', "%{$termo}%");
                    if ($termoNumerico !== '') {
                        $qq->orWhere('cpf_cnpj', 'like', "{$termoNumerico}%");
                    }
                });
            })
            ->orderBy($ordenarPor === 'cpf_cnpj' ? 'cpf_cnpj' : 'nome');
    }

    public function create()
    {
        return view('clientes.create');
    }

    public function store(Request $request)
    {
        $validado = $this->validarCliente($request);

        Cliente::create($validado);

        return redirect()->route('clientes.index')
            ->with('sucesso', 'Cliente cadastrado com sucesso.');
    }

    public function edit(Cliente $cliente)
    {
        return view('clientes.edit', compact('cliente'));
    }

    public function update(Request $request, Cliente $cliente)
    {
        $validado = $this->validarCliente($request, $cliente->id);

        $cliente->update($validado);

        return redirect()->route('clientes.index')
            ->with('sucesso', 'Cliente atualizado com sucesso.');
    }

    public function toggleAtivo(Cliente $cliente)
    {
        $cliente->update(['ativo' => ! $cliente->ativo]);

        return redirect()->route('clientes.index')
            ->with('sucesso', $cliente->ativo ? 'Cliente reativado.' : 'Cliente inativado.');
    }


/**
     * GET /clientes/buscar?q=termo
     * Usado pelo modal "Adicionar consumidor" na tela de pagamento do caixa.
     * Sem termo, devolve os primeiros em ordem alfabetica. Com termo, filtra
     * por nomes que COMECAM com o texto digitado (nao "contem").
     */
    public function buscar(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('q', ''));

        $query = Cliente::ativos();

        if ($termo !== '') {
            $termoNumerico = preg_replace('/\D/', '', $termo);
            $query->where(function ($q) use ($termo, $termoNumerico) {
                $q->where('nome', 'like', "{$termo}%");
                if ($termoNumerico !== '') {
                    $q->orWhere('cpf_cnpj', 'like', "{$termoNumerico}%");
                }
            });
        }

        $resultados = $query->orderBy('nome')->limit(20)
            ->get(['id', 'nome', 'cpf_cnpj', 'tipo_pessoa'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'nome' => $c->nome,
                'cpf_cnpj' => $c->cpf_cnpj,
                'cpf_cnpj_formatado' => $c->cpf_cnpj_formatado,
                'label' => "{$c->nome} — {$c->cpf_cnpj_formatado}",
            ]);

        return response()->json($resultados);
    }

    /**
     * POST /clientes/criar-rapido
     * Cadastro rapido a partir do modal do caixa, sem sair da tela de pagamento.
     * So exige o minimo (nome + CPF/CNPJ) - o operador pode completar o
     * endereco depois pela tela de cadastro, se precisar pra NF-e no futuro.
     */
    public function criarRapido(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipo_pessoa' => ['required', 'in:fisica,juridica'],
            'nome' => ['required', 'string', 'max:255'],
            'cpf_cnpj' => ['required', 'string', 'unique:clientes,cpf_cnpj'],
        ]);

        $data['cpf_cnpj'] = preg_replace('/\D/', '', $data['cpf_cnpj']);

        $tamanhoEsperado = $data['tipo_pessoa'] === 'fisica' ? 11 : 14;
        if (strlen($data['cpf_cnpj']) !== $tamanhoEsperado) {
            return response()->json([
                'errors' => ['cpf_cnpj' => ["Documento inválido para " . ($data['tipo_pessoa'] === 'fisica' ? 'CPF (11 dígitos)' : 'CNPJ (14 dígitos)')],
                ],
            ], 422);
        }

        $cliente = Cliente::create($data);

        return response()->json([
            'id' => $cliente->id,
            'nome' => $cliente->nome,
            'cpf_cnpj' => $cliente->cpf_cnpj,
            'cpf_cnpj_formatado' => $cliente->cpf_cnpj_formatado,
            'label' => "{$cliente->nome} — {$cliente->cpf_cnpj_formatado}",
        ]);
    }

    /**
     * GET /api/consulta-cnpj/{cnpj}
     * Consulta dados públicos de um CNPJ (BrasilAPI) para autopreencher o formulário.
     */
    public function consultarCnpj(string $cnpj): JsonResponse
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (strlen($cnpj) !== 14) {
            return response()->json(['erro' => 'CNPJ inválido.'], 422);
        }

        $response = Http::timeout(10)->get("https://brasilapi.com.br/api/cnpj/v1/{$cnpj}");

        if ($response->failed()) {
            return response()->json(['erro' => 'CNPJ não encontrado ou serviço indisponível.'], 404);
        }

        $dados = $response->json();

        return response()->json([
            'nome' => $dados['razao_social'] ?? '',
            'nome_fantasia' => $dados['nome_fantasia'] ?? '',
            'telefone' => $dados['ddd_telefone_1'] ?? '',
            'email' => $dados['email'] ?? '',
            'cep' => $dados['cep'] ?? '',
            'logradouro' => $dados['logradouro'] ?? '',
            'numero' => $dados['numero'] ?? '',
            'complemento' => $dados['complemento'] ?? '',
            'bairro' => $dados['bairro'] ?? '',
            'municipio' => $dados['municipio'] ?? '',
            'uf' => $dados['uf'] ?? '',
            'cod_municipio' => isset($dados['codigo_municipio_ibge']) ? (string) $dados['codigo_municipio_ibge'] : '',
        ]);
    }

    private function validarCliente(Request $request, $idAtual = null): array
    {
        $tipoPessoa = $request->input('tipo_pessoa');

        $validado = $request->validate([
            'tipo_pessoa' => 'required|in:fisica,juridica',
            'nome' => 'required|string|max:255',
            'nome_fantasia' => 'nullable|string|max:255',
            'cpf_cnpj' => 'required|string|unique:clientes,cpf_cnpj,' . $idAtual,
            'indicador_ie' => 'required|in:contribuinte,isento,nao_contribuinte',
            'ie' => 'required_if:indicador_ie,contribuinte|nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'cep' => 'nullable|string|size:8',
            'logradouro' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:100',
            'bairro' => 'nullable|string|max:100',
            'municipio' => 'nullable|string|max:100',
            'cod_municipio' => 'nullable|string|size:7',
            'uf' => 'nullable|string|size:2',
        ]);

        $validado['cpf_cnpj'] = preg_replace('/\D/', '', $validado['cpf_cnpj']);

        $tamanhoEsperado = $tipoPessoa === 'fisica' ? 11 : 14;
        if (strlen($validado['cpf_cnpj']) !== $tamanhoEsperado) {
            abort(422, 'Documento inválido para ' . ($tipoPessoa === 'fisica' ? 'CPF (11 dígitos)' : 'CNPJ (14 dígitos)'));
        }

        // PJ isento/contribuinte tem IE relevante; PF nunca tem
        if ($tipoPessoa === 'fisica') {
            $validado['indicador_ie'] = 'nao_contribuinte';
            $validado['ie'] = null;
        }

        return $validado;
    }
}