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
        $limite = $this->validarLimiteCredito($request);

        $cliente = Cliente::create($validado);
        $this->gravarLimiteCredito($cliente, $limite);

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
        $limite = $this->validarLimiteCredito($request);

        $cliente->update($validado);
        $this->gravarLimiteCredito($cliente, $limite);

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

    /** Aba "Limite de crédito": convênio (fiado) concedido manualmente pelo operador do painel. */
    private function validarLimiteCredito(Request $request): ?array
    {
        if (!$request->has('limite_credito_form')) {
            return null; // formulário sem a aba (ex.: cadastro por outra tela)
        }

        // Aceita "1.500,00" (digitado com vírgula) e "1500.00"
        $valor = trim((string) $request->input('limite_credito', ''));
        if (str_contains($valor, ',')) {
            $valor = str_replace(',', '.', str_replace('.', '', $valor));
        }
        $request->merge(['limite_credito' => $valor === '' ? '0' : $valor]);

        $dados = $request->validate([
            'limite_credito_ativo'      => 'nullable|boolean',
            'limite_credito'            => 'required_if:limite_credito_ativo,1|nullable|numeric|min:0|max:9999999',
            'limite_credito_dias'       => 'required_if:limite_credito_ativo,1|nullable|integer|min:1|max:365',
            'limite_credito_observacao' => 'nullable|string|max:255',
        ], [
            'limite_credito.required_if'      => 'Informe o valor do limite de crédito.',
            'limite_credito.numeric'          => 'Informe um valor válido para o limite de crédito.',
            'limite_credito_dias.required_if' => 'Informe o prazo de vencimento do convênio.',
            'limite_credito_dias.max'         => 'O prazo de vencimento pode ser de no máximo 365 dias.',
        ]);

        $ativo = (bool) ($dados['limite_credito_ativo'] ?? false);
        if ($ativo && (float) $dados['limite_credito'] <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'limite_credito' => 'Para conceder o convênio, o limite precisa ser maior que zero.',
            ]);
        }

        return [
            'ativo'      => $ativo,
            'limite'     => (float) ($dados['limite_credito'] ?? 0),
            'dias'       => (int) ($dados['limite_credito_dias'] ?? 30),
            'observacao' => $dados['limite_credito_observacao'] ?? null,
        ];
    }

    private function gravarLimiteCredito(Cliente $cliente, ?array $limite): void
    {
        if ($limite === null) {
            return;
        }

        app(\App\Services\ConvenioService::class)->definirLimite(
            $cliente, $limite['ativo'], $limite['limite'], $limite['dias'], $limite['observacao'], auth()->id()
        );
    }

    private function validarCliente(Request $request, $idAtual = null): array
    {
        $tipoPessoa = $request->input('tipo_pessoa');

        // O campo tem máscara (00000-000): valida e grava só os dígitos, como em fornecedores
        if ($request->filled('cep')) {
            $request->merge(['cep' => preg_replace('/\D/', '', (string) $request->input('cep'))]);
        }

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