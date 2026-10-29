<?php

namespace App\Http\Controllers;

use App\Models\Credenciadora;
use App\Models\CredenciadoraMeio;
use App\Models\Mdfe;
use App\Models\Transportador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CredenciadoraController extends Controller
{
    public function index(Request $request)
    {
        $filtro = $request->get('status', 'ativas'); // ativas | inativas | todas
        $busca  = trim((string) $request->get('busca', ''));
        $doc    = Transportador::normalizarDocumento($busca);

        $credenciadoras = Credenciadora::with('meios')
            ->when($filtro === 'ativas', fn ($q) => $q->where('ativo', true))
            ->when($filtro === 'inativas', fn ($q) => $q->where('ativo', false))
            ->when($busca !== '', function ($q) use ($busca, $doc) {
                $q->where(function ($q) use ($busca, $doc) {
                    $q->where('nome', 'like', "%{$busca}%");
                    if ($doc !== '') {
                        $q->orWhere('cnpj', 'like', "%{$doc}%");
                    }
                });
            })
            ->orderBy('nome')
            ->paginate(15)
            ->withQueryString();

        if ($request->ajax()) {
            return view('credenciadoras._tabela', compact('credenciadoras'));
        }

        return view('credenciadoras.index', compact('credenciadoras', 'filtro'));
    }

    public function create()
    {
        return view('credenciadoras.create', $this->dadosDoFormulario());
    }

    public function store(Request $request)
    {
        [$dados, $meios] = $this->validarDados($request);

        DB::transaction(function () use ($dados, $meios) {
            $credenciadora = Credenciadora::create($dados);
            $this->sincronizarMeios($credenciadora, $meios);
        });

        return redirect()->route('credenciadoras.index')->with('sucesso', 'Credenciadora cadastrada com sucesso.');
    }

    public function edit(Credenciadora $credenciadora)
    {
        $credenciadora->load('meios');

        return view('credenciadoras.edit', $this->dadosDoFormulario($credenciadora) + compact('credenciadora'));
    }

    public function update(Request $request, Credenciadora $credenciadora)
    {
        [$dados, $meios] = $this->validarDados($request, $credenciadora->id);

        DB::transaction(function () use ($credenciadora, $dados, $meios) {
            // "ativo" não é tocado aqui: quem muda é o botão Inativar/Reativar da listagem
            $credenciadora->update($dados);
            $this->sincronizarMeios($credenciadora, $meios);
        });

        return redirect()->route('credenciadoras.index')->with('sucesso', 'Credenciadora atualizada com sucesso.');
    }

    public function toggleAtivo(Credenciadora $credenciadora)
    {
        $credenciadora->update(['ativo' => !$credenciadora->ativo]);

        return back()->with('sucesso', $credenciadora->ativo ? 'Credenciadora reativada.' : 'Credenciadora inativada.');
    }

    // ------------------------------------------------------------------

    /** Meios já vinculados a OUTRA credenciadora (para avisar no formulário). */
    private function dadosDoFormulario(?Credenciadora $atual = null): array
    {
        $ocupados = CredenciadoraMeio::with('credenciadora:id,nome')
            ->when($atual, fn ($q) => $q->where('credenciadora_id', '!=', $atual->id))
            ->get()
            ->mapWithKeys(fn ($m) => [$m->meio_pagamento => $m->credenciadora?->nome])
            ->all();

        return ['meiosDisponiveis' => Credenciadora::opcoesDeMeio(), 'meiosOcupados' => $ocupados];
    }

    private function validarDados(Request $request, ?int $ignorarId = null): array
    {
        $request->merge([
            'cnpj' => Transportador::normalizarDocumento($request->input('cnpj')),
            'cep'  => preg_replace('/\D/', '', (string) $request->input('cep')) ?: null,
            'uf'   => strtoupper(trim((string) $request->input('uf'))) ?: null,
        ]);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'min:2', 'max:100'],
            'cnpj' => [
                'required',
                Rule::unique('credenciadoras', 'cnpj')->ignore($ignorarId),
                fn ($attr, $valor, $fail) => (strlen($valor) === 14 && Transportador::documentoValido($valor)) || $fail('CNPJ inválido.'),
            ],
            'cep'           => ['required', 'regex:/^\d{8}$/'],
            'logradouro'    => ['required', 'string', 'max:60'],
            'numero'        => ['nullable', 'string', 'max:10'],
            'complemento'   => ['nullable', 'string', 'max:60'],
            'bairro'        => ['required', 'string', 'max:60'],
            'municipio'     => ['required', 'string', 'max:100'],
            'cod_municipio' => ['required', 'regex:/^\d{7}$/'],
            'uf'            => ['required', Rule::in(Transportador::UFS)],
            'meios'         => ['nullable', 'array'],
            'meios.*'       => ['string', Rule::in(Credenciadora::MEIOS_ELETRONICOS)],
        ], [
            'cnpj.unique'            => 'Já existe uma credenciadora com esse CNPJ.',
            'cep.regex'              => 'O CEP deve ter 8 dígitos.',
            'cod_municipio.required' => 'Selecione o município na lista para preencher o código IBGE.',
            'cod_municipio.regex'    => 'Selecione o município na lista para preencher o código IBGE.',
        ]);

        if (Mdfe::ufDoMunicipio($dados['cod_municipio']) !== $dados['uf']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'cod_municipio' => 'O código IBGE do município não confere com a UF. Selecione o município na lista.',
            ]);
        }

        $meios = array_values(array_unique($dados['meios'] ?? []));
        unset($dados['meios']);

        // Cada meio só pode ter uma credenciadora
        $emUso = CredenciadoraMeio::with('credenciadora:id,nome')
            ->whereIn('meio_pagamento', $meios)
            ->when($ignorarId, fn ($q) => $q->where('credenciadora_id', '!=', $ignorarId))
            ->get();

        if ($emUso->isNotEmpty()) {
            $lista = $emUso->map(fn ($m) => (\App\Models\FormaPagamento::MEIOS[$m->meio_pagamento] ?? $m->meio_pagamento) . ' (' . $m->credenciadora?->nome . ')')->implode(', ');

            throw \Illuminate\Validation\ValidationException::withMessages([
                'meios' => "Estes meios já estão em outra credenciadora: {$lista}. Desmarque lá antes de marcar aqui.",
            ]);
        }

        return [$dados, $meios];
    }

    private function sincronizarMeios(Credenciadora $credenciadora, array $meios): void
    {
        $credenciadora->meios()->whereNotIn('meio_pagamento', $meios)->delete();

        foreach ($meios as $meio) {
            $credenciadora->meios()->firstOrCreate(['meio_pagamento' => $meio]);
        }
    }
}
