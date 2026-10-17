<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\OrdemServico;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrdemServicoController extends Controller
{
    public function index(Request $request)
    {
        $ordens = OrdemServico::query()
            ->with(['cliente', 'operador'])
            ->withCount('itens')
            ->when($request->filled('busca'), function ($q) use ($request) {
                $termo = trim($request->input('busca'));
                $numero = ltrim(preg_replace('/\D/', '', $termo), '0');

                $q->where(function ($q) use ($termo, $numero) {
                    if ($numero !== '') {
                        $q->orWhere('numero', (int) $numero);
                    }
                    $q->orWhere('equipamento', 'like', "%{$termo}%")
                      ->orWhere('numero_serie', 'like', "%{$termo}%")
                      ->orWhereHas('cliente', fn ($c) => $c->where('nome', 'like', "%{$termo}%"));
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('data_de'), fn ($q) => $q->whereDate('data_abertura', '>=', $request->input('data_de')))
            ->when($request->filled('data_ate'), fn ($q) => $q->whereDate('data_abertura', '<=', $request->input('data_ate')))
            ->orderByDesc('numero')
            ->paginate(15)
            ->withQueryString();

        return view('pedidos.ordem-servico.index', compact('ordens'));
    }

    public function create()
    {
        $ordem = new OrdemServico([
            'data_abertura' => now(),
            'prioridade'    => 'normal',
            'status'        => 'aberta',
        ]);

        return view('pedidos.ordem-servico.create', $this->dadosDoFormulario($ordem));
    }

    public function store(Request $request)
    {
        [$dados, $itens] = $this->validar($request, false);

        DB::transaction(function () use ($dados, $itens) {
            $ordem = new OrdemServico($dados);
            $ordem->numero = ((int) OrdemServico::lockForUpdate()->max('numero')) + 1;
            $ordem->status = 'aberta';
            $ordem->save();

            if ($itens) {
                $ordem->itens()->createMany($itens);
            }
        });

        return redirect()->route('ordens-servico.index')
            ->with('sucesso', 'Ordem de serviço criada com sucesso.');
    }

    public function edit(OrdemServico $ordem)
    {
        if (! $ordem->podeEditar()) {
            return redirect()->route('ordens-servico.index')
                ->with('erro', "A ordem de serviço {$ordem->numeroExibicao()} está {$ordem->statusTexto()} e não pode mais ser editada.");
        }

        $ordem->load('itens');

        return view('pedidos.ordem-servico.edit', $this->dadosDoFormulario($ordem));
    }

    public function update(Request $request, OrdemServico $ordem)
    {
        if (! $ordem->podeEditar()) {
            return redirect()->route('ordens-servico.index')
                ->with('erro', "A ordem de serviço {$ordem->numeroExibicao()} não pode mais ser editada.");
        }

        [$dados, $itens] = $this->validar($request, true);

        DB::transaction(function () use ($ordem, $dados, $itens) {
            $ordem->fill($dados);
            $this->aplicarDatasDoStatus($ordem);
            $ordem->save();

            $ordem->itens()->delete();
            if ($itens) {
                $ordem->itens()->createMany($itens);
            }
        });

        return redirect()->route('ordens-servico.index')
            ->with('sucesso', "Ordem de serviço {$ordem->numeroExibicao()} atualizada.");
    }

    /** Atalho do menu "Ações": avança a OS para o próximo passo do fluxo. */
    public function alterarStatus(Request $request, OrdemServico $ordem)
    {
        $novo = $request->input('status');

        if (! array_key_exists($novo, $ordem->proximosStatus())) {
            return redirect()->route('ordens-servico.index')
                ->with('erro', "Não é possível mudar a OS {$ordem->numeroExibicao()} de {$ordem->statusTexto()} para esse status.");
        }

        $ordem->status = $novo;
        $this->aplicarDatasDoStatus($ordem);
        $ordem->save();

        return redirect()->route('ordens-servico.index')
            ->with('sucesso', "OS {$ordem->numeroExibicao()}: {$ordem->statusTexto()}.");
    }

    public function cancelar(OrdemServico $ordem)
    {
        if (! $ordem->podeCancelar()) {
            return redirect()->route('ordens-servico.index')
                ->with('erro', "A OS {$ordem->numeroExibicao()} não pode ser cancelada.");
        }

        $ordem->update(['status' => 'cancelada']);

        return redirect()->route('ordens-servico.index')
            ->with('sucesso', "OS {$ordem->numeroExibicao()} cancelada.");
    }

    public function imprimir(OrdemServico $ordem)
    {
        return view('pedidos.ordem-servico.imprimir', $this->dadosDeImpressao($ordem));
    }

    public function cupom(OrdemServico $ordem)
    {
        return view('pedidos.ordem-servico.cupom', $this->dadosDeImpressao($ordem));
    }

    // ------------------------------------------------------------------

    private function dadosDoFormulario(OrdemServico $ordem): array
    {
        return [
            'ordem'            => $ordem,
            // AJUSTE: use aqui a mesma consulta de operadores do PedidoVendaController
            // (por exemplo, só quem tem acesso do tipo fiscal).
            'operadores'       => User::query()->where('ativo', true)->orderBy('name')->get(),
            'operadorPadraoId' => Auth::guard('web')->id(),
            'formasPagamento'  => FormaPagamento::query()->orderBy('descricao')->get(),
        ];
    }

    private function dadosDeImpressao(OrdemServico $ordem): array
    {
        $ordem->load(['cliente', 'operador', 'tecnico', 'formaPagamento', 'itens']);

        return [
            'ordem'   => $ordem,
            // AJUSTE: use a mesma forma de obter a empresa do PedidoVendaController.
            'empresa' => Empresa::query()->first() ?? new Empresa(),
        ];
    }

    /** Preenche as datas de conclusão/entrega quando o status é alcançado pela primeira vez. */
    private function aplicarDatasDoStatus(OrdemServico $ordem): void
    {
        if (in_array($ordem->status, ['concluida', 'entregue'], true) && ! $ordem->concluida_em) {
            $ordem->concluida_em = now();
        }
        if ($ordem->status === 'entregue' && ! $ordem->entregue_em) {
            $ordem->entregue_em = now();
        }
    }

    /** @return array{0: array, 1: array} [dados da OS, itens] */
    private function validar(Request $request, bool $edicao): array
    {
        $statusPermitidos = array_diff(array_keys(OrdemServico::STATUS), ['cancelada']);

        $regras = [
            'data_abertura'      => ['required', 'date'],
            'previsao_entrega'   => ['nullable', 'date', 'after_or_equal:data_abertura'],
            'operador_id'        => ['required', Rule::exists(User::class, 'id')],
            'tecnico_id'         => ['nullable', Rule::exists(User::class, 'id')],
            'cliente_id'         => ['required', Rule::exists(Cliente::class, 'id')],
            'forma_pagamento_id' => ['nullable', Rule::exists(FormaPagamento::class, 'id')],
            'prioridade'         => ['required', Rule::in(['normal', 'urgente'])],
            'status'             => $edicao ? ['required', Rule::in($statusPermitidos)] : ['nullable'],

            'equipamento'        => ['required', 'string', 'max:150'],
            'marca'              => ['nullable', 'string', 'max:80'],
            'modelo'             => ['nullable', 'string', 'max:80'],
            'numero_serie'       => ['nullable', 'string', 'max:80'],
            'acessorios'         => ['nullable', 'string', 'max:255'],

            'defeito_relatado'   => ['required', 'string', 'max:2000'],
            'condicao_entrada'   => ['nullable', 'string', 'max:1000'],
            'laudo_tecnico'      => ['nullable', 'string', 'max:2000'],
            'servico_executado'  => ['nullable', 'string', 'max:2000'],
            'observacoes'        => ['nullable', 'string', 'max:1000'],

            'garantia_dias'      => ['nullable', 'integer', 'min:0', 'max:3650'],
            'valor_servico'      => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'desconto'           => ['nullable', 'numeric', 'min:0', 'max:9999999999'],

            'itens'                       => ['nullable', 'array'],
            'itens.*.produto_id'          => ['required', Rule::exists(Produto::class, 'id')],
            'itens.*.produto_variante_id' => ['nullable', 'integer'],
            'itens.*.codigo_barras'       => ['nullable', 'string', 'max:30'],
            'itens.*.descricao'           => ['required', 'string', 'max:255'],
            'itens.*.unidade'             => ['required', 'string', 'max:6'],
            'itens.*.quantidade'          => ['required', 'numeric', 'gt:0'],
            'itens.*.valor_unitario'      => ['required', 'numeric', 'min:0'],
        ];

        $nomes = [
            'data_abertura'     => 'data de abertura',
            'previsao_entrega'  => 'previsão de entrega',
            'operador_id'       => 'operador',
            'tecnico_id'        => 'técnico',
            'cliente_id'        => 'cliente',
            'forma_pagamento_id' => 'forma de pagamento',
            'defeito_relatado'  => 'defeito relatado',
            'condicao_entrada'  => 'condição do equipamento',
            'laudo_tecnico'     => 'laudo técnico',
            'servico_executado' => 'serviço executado',
            'numero_serie'      => 'número de série',
            'garantia_dias'     => 'garantia',
            'valor_servico'     => 'mão de obra',
            'itens.*.quantidade'     => 'quantidade',
            'itens.*.valor_unitario' => 'valor unitário',
        ];

        $dados = $request->validate($regras, [], $nomes);

        $itens = collect($dados['itens'] ?? [])->map(fn ($i) => [
            'produto_id'          => $i['produto_id'],
            'produto_variante_id' => $i['produto_variante_id'] ?? null,
            'codigo_barras'       => $i['codigo_barras'] ?? null,
            'descricao'           => $i['descricao'],
            'unidade'             => strtoupper($i['unidade']),
            'quantidade'          => round((float) $i['quantidade'], 3),
            'valor_unitario'      => round((float) $i['valor_unitario'], 2),
        ])->values()->all();

        $pecas   = round(collect($itens)->sum(fn ($i) => round($i['quantidade'] * $i['valor_unitario'], 2)), 2);
        $servico = round((float) ($dados['valor_servico'] ?? 0), 2);
        $desconto = round((float) ($dados['desconto'] ?? 0), 2);

        if ($desconto > $pecas + $servico) {
            throw ValidationException::withMessages([
                'desconto' => 'O desconto não pode ser maior que o total de peças e serviços.',
            ]);
        }

        unset($dados['itens']);
        $dados['valor_servico'] = $servico;
        $dados['desconto']      = $desconto;
        $dados['valor_total']   = round($pecas + $servico - $desconto, 2);

        return [$dados, $itens];
    }
}
