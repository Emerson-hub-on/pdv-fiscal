<?php

namespace App\Http\Controllers;

use App\Models\ContaPagar;
use App\Models\ContaPagarParcela;
use App\Models\FormaPagamento;
use App\Models\Fornecedor;
use App\Models\TipoContaPagar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContaPagarController extends Controller
{
    public function index(Request $request)
    {
        $contas = ContaPagar::with(['tipo', 'fornecedor', 'parcelas'])
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = trim($request->busca);

                $q->where(function ($q) use ($busca) {
                    $q->where('descricao', 'like', "%{$busca}%")
                      ->orWhere('numero_documento', 'like', "%{$busca}%")
                      ->orWhereHas('fornecedor', fn ($f) => $f->where('nome', 'like', "%{$busca}%"));
                });
            })
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo_conta_pagar_id', $request->tipo))
            ->when($request->status === 'aberta', fn ($q) => $q->where('status', 'aberta'))
            ->when($request->status === 'paga', fn ($q) => $q->where('status', 'paga'))
            ->when($request->status === 'vencida', function ($q) {
                $q->whereHas('parcelas', fn ($p) => $p->whereNull('data_pagamento')->whereDate('vencimento', '<', today()));
            })
            ->when($request->filled('venc_de') || $request->filled('venc_ate'), function ($q) use ($request) {
                $q->whereHas('parcelas', function ($p) use ($request) {
                    $p->when($request->filled('venc_de'), fn ($x) => $x->whereDate('vencimento', '>=', $request->venc_de))
                      ->when($request->filled('venc_ate'), fn ($x) => $x->whereDate('vencimento', '<=', $request->venc_ate));
                });
            })
            ->latest('data_conta')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $pendentes = ContaPagarParcela::whereNull('data_pagamento');

        $resumo = [
            'aberto'   => (float) (clone $pendentes)->sum('valor'),
            'vencido'  => (float) (clone $pendentes)->whereDate('vencimento', '<', today())->sum('valor'),
            'proximos' => (float) (clone $pendentes)
                ->whereDate('vencimento', '>=', today())
                ->whereDate('vencimento', '<=', today()->addDays(7))
                ->sum('valor'),
        ];

        $tipos = TipoContaPagar::orderBy('descricao')->get();

        return view('financeiro.contas-pagar.index', compact('contas', 'resumo', 'tipos'));
    }

    public function create()
    {
        $conta = new ContaPagar(['data_conta' => today()]);

        return view('financeiro.contas-pagar.create', $this->dadosFormulario($conta));
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $this->validar($request);
        $parcelas = $this->parcelasValidadas($dados, $request, 0);

        DB::transaction(function () use ($dados, $parcelas) {
            $conta = ContaPagar::create($this->camposConta($dados) + ['status' => 'aberta']);
            $this->gravarParcelas($conta, $parcelas);
        });

        return redirect()->route('contas-pagar.index')->with('sucesso', 'Conta cadastrada com sucesso.');
    }

    public function edit(ContaPagar $conta)
    {
        $conta->load('parcelas');

        return view('financeiro.contas-pagar.edit', $this->dadosFormulario($conta));
    }

    public function update(Request $request, ContaPagar $conta): RedirectResponse
    {
        $dados = $this->validar($request);
        $conta->load('parcelas');

        // parcelas já pagas ficam intocadas; só as pendentes são substituídas pelas enviadas
        $pagoCent = $conta->parcelas
            ->filter(fn ($p) => $p->isPaga())
            ->sum(fn ($p) => $this->centavos($p->valor));

        $parcelas = $this->parcelasValidadas($dados, $request, $pagoCent);

        DB::transaction(function () use ($conta, $dados, $parcelas) {
            $conta->update($this->camposConta($dados));
            $conta->parcelas()->whereNull('data_pagamento')->delete();
            $this->gravarParcelas($conta, $parcelas);
        });

        return redirect()->route('contas-pagar.index')->with('sucesso', 'Conta atualizada com sucesso.');
    }

    public function destroy(ContaPagar $conta): RedirectResponse
    {
        if ($conta->parcelas()->whereNotNull('data_pagamento')->exists()) {
            return back()->with('erro', 'Esta conta possui parcelas pagas. Estorne os pagamentos antes de excluir.');
        }

        $conta->delete(); // as parcelas saem junto (cascade)

        return redirect()->route('contas-pagar.index')->with('sucesso', 'Conta excluída.');
    }

    // ---------------------------------------------------------------
    // Baixa / estorno de parcela
    // ---------------------------------------------------------------
    public function baixar(Request $request, ContaPagarParcela $parcela): RedirectResponse
    {
        if ($parcela->isPaga()) {
            return back()->with('erro', 'Esta parcela já foi baixada.');
        }

        $dados = $request->validate([
            'data_pagamento'     => ['required', 'date'],
            'valor_pago'         => ['required', 'numeric', 'min:0.01'],
            'forma_pagamento_id' => ['nullable', 'integer'],
        ], [
            'data_pagamento.required' => 'Informe a data do pagamento.',
            'valor_pago.required'     => 'Informe o valor pago.',
            'valor_pago.min'          => 'O valor pago deve ser maior que zero.',
        ]);

        $parcela->update($dados);
        $parcela->conta->recalcularStatus();

        return back()->with('sucesso', "Parcela {$parcela->numero} baixada.");
    }

    public function estornar(ContaPagarParcela $parcela): RedirectResponse
    {
        if (! $parcela->isPaga()) {
            return back()->with('erro', 'Esta parcela não está paga.');
        }

        $parcela->update([
            'data_pagamento'     => null,
            'valor_pago'         => null,
            'forma_pagamento_id' => null,
        ]);
        $parcela->conta->recalcularStatus();

        return back()->with('sucesso', "Pagamento da parcela {$parcela->numero} estornado.");
    }

    // ---------------------------------------------------------------
    // Auxiliares
    // ---------------------------------------------------------------
    private function dadosFormulario(ContaPagar $conta): array
    {
        return [
            'conta'          => $conta,
            'tipos'          => TipoContaPagar::where('ativo', true)->orderBy('descricao')->get(),
            'fornecedores'   => Fornecedor::orderBy('nome')->get(),
            'formasPagamento' => FormaPagamento::orderBy('descricao')->get(),
        ];
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'tipo_conta_pagar_id'   => ['required', 'exists:tipos_conta_pagar,id'],
            'fornecedor_id'         => ['nullable', 'exists:fornecedores,id'],
            'descricao'             => ['required', 'string', 'max:150'],
            'numero_documento'      => ['nullable', 'string', 'max:30'],
            'data_conta'            => ['required', 'date'],
            'valor_total'           => ['required', 'numeric', 'min:0.01'],
            'vencimento'            => ['nullable', 'date'],
            'observacao'            => ['nullable', 'string', 'max:1000'],
            'parcelas'              => ['nullable', 'array', 'max:120'],
            'parcelas.*.vencimento' => ['required', 'date'],
            'parcelas.*.valor'      => ['required', 'numeric', 'min:0.01'],
        ], [
            'tipo_conta_pagar_id.required' => 'Selecione o tipo da conta.',
            'descricao.required'           => 'Informe a descrição da conta.',
            'data_conta.required'          => 'Informe a data da conta.',
            'valor_total.required'         => 'Informe o valor total.',
            'valor_total.min'              => 'O valor total deve ser maior que zero.',
            'parcelas.*.vencimento.required' => 'Informe o vencimento de todas as parcelas.',
            'parcelas.*.valor.required'      => 'Informe o valor de todas as parcelas.',
            'parcelas.*.valor.min'           => 'O valor de cada parcela deve ser maior que zero.',
        ]);
    }

    private function camposConta(array $dados): array
    {
        return Arr::only($dados, [
            'tipo_conta_pagar_id', 'fornecedor_id', 'descricao',
            'numero_documento', 'data_conta', 'valor_total', 'observacao',
        ]);
    }

    /**
     * Devolve a lista [vencimento, valor] das parcelas pendentes a gravar,
     * garantindo que (já pago + parcelas enviadas) == valor total.
     * Sem parcelas na tela, cria uma parcela única com o vencimento do cabeçalho.
     */
    private function parcelasValidadas(array $dados, Request $request, int $pagoCent): array
    {
        $totalCent = $this->centavos($dados['valor_total']);
        $restante  = $totalCent - $pagoCent;
        $enviadas  = collect($request->input('parcelas', []))->values();

        if ($enviadas->isEmpty()) {
            if ($restante < 0) {
                throw ValidationException::withMessages([
                    'valor_total' => 'O valor total não pode ser menor que o já pago (R$ ' . $this->moeda($pagoCent) . ').',
                ]);
            }

            if ($restante === 0) {
                return [];
            }

            if (empty($dados['vencimento'])) {
                throw ValidationException::withMessages(['vencimento' => 'Informe o vencimento.']);
            }

            return [['vencimento' => $dados['vencimento'], 'valor' => $restante / 100]];
        }

        $somaCent = $enviadas->sum(fn ($p) => $this->centavos($p['valor']));

        if ($somaCent !== $restante) {
            $referencia = $pagoCent > 0
                ? 'ao valor restante a pagar (R$ ' . $this->moeda($restante) . ')'
                : 'ao valor total (R$ ' . $this->moeda($totalCent) . ')';

            throw ValidationException::withMessages([
                'parcelas' => 'A soma das parcelas (R$ ' . $this->moeda($somaCent) . ') precisa ser igual ' . $referencia . '.',
            ]);
        }

        return $enviadas->map(fn ($p) => [
            'vencimento' => $p['vencimento'],
            'valor'      => $this->centavos($p['valor']) / 100,
        ])->all();
    }

    private function gravarParcelas(ContaPagar $conta, array $parcelas): void
    {
        foreach ($parcelas as $p) {
            $conta->parcelas()->create([
                'numero'     => 0, // numeração definitiva logo abaixo
                'vencimento' => $p['vencimento'],
                'valor'      => $p['valor'],
            ]);
        }

        // numera todas (inclusive as já pagas) por ordem de vencimento
        $conta->parcelas()->orderBy('vencimento')->orderBy('id')->get()
            ->each(fn ($p, $i) => $p->update(['numero' => $i + 1]));

        $conta->recalcularStatus();
    }

    private function centavos(mixed $valor): int
    {
        return (int) round(((float) $valor) * 100);
    }

    private function moeda(int $centavos): string
    {
        return number_format($centavos / 100, 2, ',', '.');
    }
}
