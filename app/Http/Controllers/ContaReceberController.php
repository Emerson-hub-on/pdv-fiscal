<?php

namespace App\Http\Controllers;

use App\Models\ContaReceber;
use App\Models\ContaReceberParcela;
use App\Models\FormaPagamento;
use App\Models\Cliente;
use App\Models\TipoContaReceber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContaReceberController extends Controller
{
    public function index(Request $request)
    {
        $contas = ContaReceber::with(['tipo', 'cliente', 'parcelas'])
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = trim($request->busca);

                $q->where(function ($q) use ($busca) {
                    $q->where('descricao', 'like', "%{$busca}%")
                      ->orWhere('numero_documento', 'like', "%{$busca}%")
                      ->orWhereHas('cliente', fn ($c) => $c->where($this->colunaNomeCliente(), 'like', "%{$busca}%"));
                });
            })
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo_conta_receber_id', $request->tipo))
            ->when($request->status === 'aberta', fn ($q) => $q->where('status', 'aberta'))
            ->when($request->status === 'recebida', fn ($q) => $q->where('status', 'recebida'))
            ->when($request->status === 'vencida', function ($q) {
                $q->whereHas('parcelas', fn ($p) => $p->whereNull('data_recebimento')->whereDate('vencimento', '<', today()));
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

        $pendentes = ContaReceberParcela::whereNull('data_recebimento');

        $resumo = [
            'aberto'   => (float) (clone $pendentes)->sum('valor'),
            'vencido'  => (float) (clone $pendentes)->whereDate('vencimento', '<', today())->sum('valor'),
            'proximos' => (float) (clone $pendentes)
                ->whereDate('vencimento', '>=', today())
                ->whereDate('vencimento', '<=', today()->addDays(7))
                ->sum('valor'),
        ];

        $tipos = TipoContaReceber::orderBy('descricao')->get();

        return view('financeiro.contas-receber.index', compact('contas', 'resumo', 'tipos'));
    }

    public function create()
    {
        $conta = new ContaReceber(['data_conta' => today()]);

        return view('financeiro.contas-receber.create', $this->dadosFormulario($conta));
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $this->validar($request);
        $parcelas = $this->parcelasValidadas($dados, $request, 0);

        DB::transaction(function () use ($dados, $parcelas) {
            $conta = ContaReceber::create($this->camposConta($dados) + ['status' => 'aberta']);
            $this->gravarParcelas($conta, $parcelas);
        });

        return redirect()->route('contas-receber.index')->with('sucesso', 'Conta cadastrada com sucesso.');
    }

    public function edit(ContaReceber $conta)
    {
        $conta->load('parcelas');

        return view('financeiro.contas-receber.edit', $this->dadosFormulario($conta));
    }

    public function update(Request $request, ContaReceber $conta): RedirectResponse
    {
        $dados = $this->validar($request);
        $conta->load('parcelas');

        // parcelas já recebidas ficam intocadas; só as pendentes são substituídas pelas enviadas
        $recebidoCent = $conta->parcelas
            ->filter(fn ($p) => $p->isRecebida())
            ->sum(fn ($p) => $this->centavos($p->valor));

        $parcelas = $this->parcelasValidadas($dados, $request, $recebidoCent);

        DB::transaction(function () use ($conta, $dados, $parcelas) {
            $conta->update($this->camposConta($dados));
            $conta->parcelas()->whereNull('data_recebimento')->delete();
            $this->gravarParcelas($conta, $parcelas);
        });

        return redirect()->route('contas-receber.index')->with('sucesso', 'Conta atualizada com sucesso.');
    }

    public function destroy(ContaReceber $conta): RedirectResponse
    {
        if ($conta->parcelas()->whereNotNull('data_recebimento')->exists()) {
            return back()->with('erro', 'Esta conta possui parcelas recebidas. Estorne os recebimentos antes de excluir.');
        }

        $conta->delete(); // as parcelas saem junto (cascade)

        return redirect()->route('contas-receber.index')->with('sucesso', 'Conta excluída.');
    }

    // ---------------------------------------------------------------
    // Baixa / estorno de parcela
    // ---------------------------------------------------------------
    public function baixar(Request $request, ContaReceberParcela $parcela): RedirectResponse
    {
        if ($parcela->isRecebida()) {
            return back()->with('erro', 'Esta parcela já foi baixada.');
        }

        $dados = $request->validate([
            'data_recebimento'     => ['required', 'date'],
            'valor_recebido'         => ['required', 'numeric', 'min:0.01'],
            'forma_pagamento_id' => ['nullable', 'integer'],
        ], [
            'data_recebimento.required' => 'Informe a data do recebimento.',
            'valor_recebido.required'     => 'Informe o valor recebido.',
            'valor_recebido.min'          => 'O valor recebido deve ser maior que zero.',
        ]);

        $parcela->update($dados);
        $parcela->conta->recalcularStatus();

        return back()->with('sucesso', "Parcela {$parcela->numero} baixada.");
    }

    public function estornar(ContaReceberParcela $parcela): RedirectResponse
    {
        if (! $parcela->isRecebida()) {
            return back()->with('erro', 'Esta parcela não está recebida.');
        }

        $parcela->update([
            'data_recebimento'     => null,
            'valor_recebido'         => null,
            'forma_pagamento_id' => null,
        ]);
        $parcela->conta->recalcularStatus();

        return back()->with('sucesso', "Recebimento da parcela {$parcela->numero} estornado.");
    }

    // ---------------------------------------------------------------
    // Auxiliares
    // ---------------------------------------------------------------
    /** Coluna de nome da tabela de clientes (nome, razao_social ou nome_fantasia, a primeira que existir). */
    private function colunaNomeCliente(): string
    {
        static $coluna = null;

        return $coluna ??= collect(['nome', 'razao_social', 'nome_fantasia'])
            ->first(fn ($c) => Schema::hasColumn((new Cliente)->getTable(), $c), 'id');
    }

    private function dadosFormulario(ContaReceber $conta): array
    {
        return [
            'conta'          => $conta,
            'tipos'          => TipoContaReceber::where('ativo', true)->orderBy('descricao')->get(),
            'clientes'       => Cliente::orderBy($this->colunaNomeCliente())->get(),
            'formasPagamento' => FormaPagamento::orderBy('descricao')->get(),
        ];
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'tipo_conta_receber_id'   => ['required', 'exists:tipos_conta_receber,id'],
            'cliente_id'            => ['nullable', Rule::exists((new Cliente)->getTable(), 'id')],
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
            'tipo_conta_receber_id.required' => 'Selecione o tipo da conta.',
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
            'tipo_conta_receber_id', 'cliente_id', 'descricao',
            'numero_documento', 'data_conta', 'valor_total', 'observacao',
        ]);
    }

    /**
     * Devolve a lista [vencimento, valor] das parcelas pendentes a gravar,
     * garantindo que (já recebido + parcelas enviadas) == valor total.
     * Sem parcelas na tela, cria uma parcela única com o vencimento do cabeçalho.
     */
    private function parcelasValidadas(array $dados, Request $request, int $recebidoCent): array
    {
        $totalCent = $this->centavos($dados['valor_total']);
        $restante  = $totalCent - $recebidoCent;
        $enviadas  = collect($request->input('parcelas', []))->values();

        if ($enviadas->isEmpty()) {
            if ($restante < 0) {
                throw ValidationException::withMessages([
                    'valor_total' => 'O valor total não pode ser menor que o já recebido (R$ ' . $this->moeda($recebidoCent) . ').',
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
            $referencia = $recebidoCent > 0
                ? 'ao valor restante a receber (R$ ' . $this->moeda($restante) . ')'
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

    private function gravarParcelas(ContaReceber $conta, array $parcelas): void
    {
        foreach ($parcelas as $p) {
            $conta->parcelas()->create([
                'numero'     => 0, // numeração definitiva logo abaixo
                'vencimento' => $p['vencimento'],
                'valor'      => $p['valor'],
            ]);
        }

        // numera todas (inclusive as já recebidas) por ordem de vencimento
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
