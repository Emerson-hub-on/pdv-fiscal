<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\OrdemServico;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrdemServicoController extends Controller
{
    private ?Collection $operadoresCache = null;
    private ?Collection $tecnicosCache = null;

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
                      ->orWhere('cliente_nome', 'like', "%{$termo}%")
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

        [$dados, $itens] = $this->validar($request, true, $ordem);

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

    /**
     * Busca de peças para a OS: só produtos ATIVOS do cadastro. Produto com variação
     * não entra "inteiro": cada variação vira uma linha (com o código de barras dela).
     */
    public function buscarProduto(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('termo'));

        if ($termo === '') {
            return response()->json([]);
        }

        $like = "%{$termo}%";

        $produtos = Produto::query()
            ->ativos()
            ->where(function ($q) use ($like) {
                $q->where('nome', 'like', $like)
                  ->orWhere('codigo_interno', 'like', $like)
                  ->orWhere('codigo_barras', 'like', $like)
                  ->orWhere('referencia', 'like', $like)
                  ->orWhereHas('variantes', fn ($v) => $v
                      ->where('codigo_barras', 'like', $like)
                      ->orWhere('cor', 'like', $like)
                      ->orWhere('tamanho', 'like', $like));
            })
            ->with('variantes')
            ->orderByRaw('(codigo_barras = ? OR codigo_interno = ?) DESC', [$termo, $termo])
            ->orderBy('nome')
            ->limit(30)
            ->get();

        $linhas = [];

        foreach ($produtos as $produto) {
            $unidade = $produto->unidade_comercial ?: 'UN';
            $preco   = (float) $produto->preco_venda;

            if ($produto->tem_variacao) {
                foreach ($produto->variantes as $variante) {
                    $linhas[] = [
                        'produto_id'          => $produto->id,
                        'produto_variante_id' => $variante->id,
                        'codigo_barras'       => $variante->codigo_barras,
                        'codigo_interno'      => $produto->codigo_interno,
                        'descricao'           => $produto->nome . ' — ' . $variante->rotulo(),
                        'unidade'             => $unidade,
                        'estoque'             => (float) $variante->estoque,
                        'preco_venda'         => $preco,
                    ];
                }
                continue;
            }

            $linhas[] = [
                'produto_id'          => $produto->id,
                'produto_variante_id' => null,
                'codigo_barras'       => $produto->codigo_barras,
                'codigo_interno'      => $produto->codigo_interno,
                'descricao'           => $produto->nome,
                'unidade'             => $unidade,
                'estoque'             => (float) $produto->estoque,
                'preco_venda'         => $preco,
            ];
        }

        return response()->json(array_slice($linhas, 0, 50));
    }

    /**
     * Monta os itens a partir do CADASTRO de produtos. Do formulário só valem produto,
     * variação, quantidade e valor unitário; descrição, unidade e código de barras
     * são sempre os do cadastro (o navegador não consegue inventar uma peça).
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolverItensDoCadastro(array $itensEnviados, ?OrdemServico $ordem): array
    {
        if (! $itensEnviados) {
            return [];
        }

        $produtos = Produto::with('variantes')
            ->whereIn('id', collect($itensEnviados)->pluck('produto_id'))
            ->get()
            ->keyBy('id');

        // peças que já estavam na OS continuam valendo mesmo que o produto tenha sido inativado depois
        $jaNaOrdem = $ordem
            ? $ordem->itens->map(fn ($i) => $i->produto_id . '-' . ($i->produto_variante_id ?? 0))->all()
            : [];

        $resultado = [];

        foreach (array_values($itensEnviados) as $indice => $item) {
            $campo   = "itens.{$indice}.produto_id";
            $produto = $produtos->get($item['produto_id']);

            if (! $produto) {
                throw ValidationException::withMessages([$campo => 'Peça ' . ($indice + 1) . ': produto não encontrado no cadastro.']);
            }

            $variante = null;
            $idVariante = ! empty($item['produto_variante_id']) ? (int) $item['produto_variante_id'] : null;

            if ($produto->tem_variacao) {
                $variante = $produto->variantes->firstWhere('id', $idVariante);

                if (! $variante) {
                    throw ValidationException::withMessages([$campo => "Peça " . ($indice + 1) . ": escolha uma variação de \"{$produto->nome}\"."]);
                }
            } elseif ($idVariante) {
                throw ValidationException::withMessages([$campo => "Peça " . ($indice + 1) . ": \"{$produto->nome}\" não tem variações."]);
            }

            $chave = $produto->id . '-' . ($variante?->id ?? 0);

            if (! $produto->ativo && ! in_array($chave, $jaNaOrdem, true)) {
                throw ValidationException::withMessages([$campo => "Peça " . ($indice + 1) . ": \"{$produto->nome}\" está inativo no cadastro."]);
            }

            $resultado[] = [
                'produto_id'          => $produto->id,
                'produto_variante_id' => $variante?->id,
                'codigo_barras'       => $variante ? $variante->codigo_barras : $produto->codigo_barras,
                'descricao'           => $variante ? $produto->nome . ' — ' . $variante->rotulo() : $produto->nome,
                'unidade'             => strtoupper($produto->unidade_comercial ?: 'UN'),
                'quantidade'          => round((float) $item['quantidade'], 3),
                'valor_unitario'      => round((float) $item['valor_unitario'], 2),
            ];
        }

        return $resultado;
    }

    // ------------------------------------------------------------------

    /**
     * Operador da OS: mesmo critério do operador da NF-e (operadores fiscais ativos e o administrador).
     */
    private function operadores(): Collection
    {
        return $this->operadoresCache ??= User::operadoresDaNota()->orderBy('name')->get();
    }

    /**
     * Técnico da OS: pessoas ativas com acesso ativo ao tipo "tecnico"
     * (Cadastros > Usuários > Técnico).
     */
    private function tecnicos(): Collection
    {
        return $this->tecnicosCache ??= User::comAcessoAtivo('tecnico')->orderBy('name')->get();
    }

    /**
     * Valida no servidor que a pessoa escolhida pertence à lista permitida. Ao editar, quem já
     * estava na OS continua valendo, mesmo que tenha sido desativado ou perdido o acesso depois.
     */
    private function regraDaLista(\Closure $lista, ?int $valorAtual, string $mensagem): \Closure
    {
        return function (string $atributo, mixed $valor, \Closure $falhar) use ($lista, $valorAtual, $mensagem) {
            if ($valorAtual !== null && (int) $valor === (int) $valorAtual) {
                return;
            }

            if (! $lista()->contains('id', (int) $valor)) {
                $falhar($mensagem);
            }
        };
    }

    private function dadosDoFormulario(OrdemServico $ordem): array
    {
        return [
            'ordem'            => $ordem,
            'operadores'       => $this->operadores(),
            'tecnicos'         => $this->tecnicos(),
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
    private function validar(Request $request, bool $edicao, ?OrdemServico $ordem = null): array
    {
        $statusPermitidos = array_diff(array_keys(OrdemServico::STATUS), ['cancelada']);

        $regras = [
            'data_abertura'      => ['required', 'date'],
            'previsao_entrega'   => ['nullable', 'date', 'after_or_equal:data_abertura'],
            'operador_id'        => ['required', Rule::exists(User::class, 'id'),
                $this->regraDaLista(fn () => $this->operadores(), $ordem?->operador_id, 'O :attribute selecionado não é um operador fiscal.')],
            'tecnico_id'         => ['nullable', Rule::exists(User::class, 'id'),
                $this->regraDaLista(fn () => $this->tecnicos(), $ordem?->tecnico_id, 'O :attribute selecionado não está cadastrado como técnico.')],
            // cliente cadastrado OU avulso: só os campos do tipo escolhido são validados
            'tipo_cliente'       => ['required', Rule::in(['cadastrado', 'avulso'])],
            'cliente_id'         => ['exclude_unless:tipo_cliente,cadastrado', 'required', Rule::exists(Cliente::class, 'id')],
            'cliente_nome'       => ['exclude_unless:tipo_cliente,avulso', 'required', 'string', 'max:150'],
            'cliente_telefone'   => ['exclude_unless:tipo_cliente,avulso', 'nullable', 'string', 'max:30'],
            'cliente_documento'  => ['exclude_unless:tipo_cliente,avulso', 'nullable', 'string', 'max:18',
                function (string $atributo, mixed $valor, \Closure $falhar) {
                    $digitos = preg_replace('/\D/', '', (string) $valor);
                    if ($digitos !== '' && ! in_array(strlen($digitos), [11, 14], true)) {
                        $falhar('O :attribute deve ter 11 dígitos (CPF) ou 14 (CNPJ).');
                    }
                },
            ],
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
            'termo_responsabilidade' => ['nullable', 'string', 'max:2000'],

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
            'cliente_nome'      => 'nome do cliente',
            'cliente_telefone'  => 'telefone do cliente',
            'cliente_documento' => 'CPF/CNPJ do cliente',
            'forma_pagamento_id' => 'forma de pagamento',
            'defeito_relatado'  => 'defeito relatado',
            'condicao_entrada'  => 'condição do equipamento',
            'laudo_tecnico'     => 'laudo técnico',
            'servico_executado' => 'serviço executado',
            'numero_serie'      => 'número de série',
            'garantia_dias'     => 'garantia',
            'termo_responsabilidade' => 'termo de responsabilidade',
            'valor_servico'     => 'mão de obra',
            'itens.*.quantidade'     => 'quantidade',
            'itens.*.valor_unitario' => 'valor unitário',
        ];

        $dados = $request->validate($regras, [], $nomes);

        $itens = $this->resolverItensDoCadastro($dados['itens'] ?? [], $ordem);

        $pecas   = round(collect($itens)->sum(fn ($i) => round($i['quantidade'] * $i['valor_unitario'], 2)), 2);
        $servico = round((float) ($dados['valor_servico'] ?? 0), 2);
        $desconto = round((float) ($dados['desconto'] ?? 0), 2);

        if ($desconto > $pecas + $servico) {
            throw ValidationException::withMessages([
                'desconto' => 'O desconto não pode ser maior que o total de peças e serviços.',
            ]);
        }

        if ($dados['tipo_cliente'] === 'avulso') {
            $dados['cliente_id']        = null;
            $dados['cliente_nome']      = trim($dados['cliente_nome']);
            $dados['cliente_telefone']  = $dados['cliente_telefone'] ?? null;
            $dados['cliente_documento'] = preg_replace('/\D/', '', (string) ($dados['cliente_documento'] ?? '')) ?: null;
        } else {
            $dados['cliente_nome'] = $dados['cliente_telefone'] = $dados['cliente_documento'] = null;
        }

        // Termo igual ao padrão (ou vazio) não é gravado: a OS continua usando o texto padrão atual
        $termo = str_replace("\r\n", "\n", trim((string) ($dados['termo_responsabilidade'] ?? '')));
        $dados['termo_responsabilidade'] = ($termo === '' || $termo === OrdemServico::TERMO_PADRAO) ? null : $termo;

        unset($dados['tipo_cliente'], $dados['itens']);
        $dados['valor_servico'] = $servico;
        $dados['desconto']      = $desconto;
        $dados['valor_total']   = round($pecas + $servico - $desconto, 2);

        return [$dados, $itens];
    }
}