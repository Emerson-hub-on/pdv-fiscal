<?php

namespace App\Http\Controllers;

use App\Models\EntradaNota;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Models\ProdutoLote;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Services\NfeXmlParser;
use App\Models\EntradaNotaItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Ncm;
use App\Models\ProdutoVariante;
use App\Models\Tributacao;
use App\Models\Empresa;
use App\Models\CfopEntrada;
use App\Models\OperacaoEntrada;
use App\Services\ConversaoFiscalEntrada;


class EntradaNotaController extends Controller
{
    public function index(Request $request)
    {
        $entradas = EntradaNota::with('fornecedor')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = trim($request->busca);

                $q->where(function ($w) use ($busca) {
                    $w->where('numero', 'like', "%{$busca}%")
                      ->orWhere('chave_acesso', 'like', "%{$busca}%")
                        ->orWhereHas('fornecedor', function ($f) use ($busca) {
                            $f->where('nome', 'like', "%{$busca}%")
                            ->orWhere('nome_fantasia', 'like', "%{$busca}%");
                            $digitos = preg_replace('/\D/', '', $busca);
                            if ($digitos !== '') {
                                $f->orWhere('cpf_cnpj', 'like', "%{$digitos}%");
                            }
                        });
                });
            })
            ->orderByDesc('data_entrada')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('notasfiscais.entrada.index', compact('entradas'));
    }

    public function salvarOpcoesXml(Request $request)
    {
        $empresa = Empresa::atual();

        $doRegime = Rule::exists('tributacoes', 'id')
            ->where(fn ($q) => $q->where('crt', $empresa->crt)->where('ativo', true));

        $d = $request->validate([
            'tributacao_padrao_id'    => ['nullable', $doRegime],
            'tributacao_st_padrao_id' => ['nullable', $doRegime],
            'pis_cofins_padrao_id'    => ['nullable', 'exists:classificacoes_pis_cofins,id'],
            'margem_padrao'           => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ], [
            'tributacao_padrao_id.exists'    => 'A tributação padrão não pertence ao regime da empresa.',
            'tributacao_st_padrao_id.exists' => 'A tributação de ST não pertence ao regime da empresa.',
        ]);

        Empresa::atual()->forceFill([
            'entrada_tributacao_padrao_id'    => $d['tributacao_padrao_id'] ?? null,
            'entrada_tributacao_st_padrao_id' => $d['tributacao_st_padrao_id'] ?? null,
            'entrada_pis_cofins_padrao_id'    => $d['pis_cofins_padrao_id'] ?? null,
            'entrada_margem_padrao'           => $d['margem_padrao'] ?? 0,
        ])->save();

        return response()->json(['ok' => true]);
    }

    public function cadastrarPendentes(Request $request)
    {
        $dados = $request->validate([
            'token'     => ['required', 'string'],
            'indices'   => ['required', 'array', 'min:1'],
            'indices.*' => ['integer', 'min:0'],
        ]);

        $nfe = Cache::get('importar_xml:' . auth()->id() . ':' . $dados['token']);
        if (! $nfe) {
            return response()->json(['message' => 'A importação expirou. Envie o XML novamente.'], 422);
        }

        $empresa = Empresa::atual();

        if (! $empresa->entrada_tributacao_padrao_id) {
            return response()->json(['message' => 'Defina a "Tributação padrão" em Opções antes de cadastrar automaticamente.'], 422);
        }
        if ($empresa->crt == 3 && ! $empresa->entrada_pis_cofins_padrao_id) {
            return response()->json(['message' => 'Defina o "PIS/COFINS padrão" em Opções (obrigatório no seu regime).'], 422);
        }

        $validas = Tributacao::where('crt', $empresa->crt)->where('ativo', true)
            ->whereIn('id', array_filter([
                $empresa->entrada_tributacao_padrao_id,
                $empresa->entrada_tributacao_st_padrao_id,
            ]))
            ->pluck('id');
        if (! $validas->contains($empresa->entrada_tributacao_padrao_id)) {
            return response()->json([
                'message' => 'A tributação padrão salva não pertence ao regime atual da empresa. Abra Opções e escolha novamente.',
            ], 422);
        }
        // padrão de ST de outro regime é ignorado: usa a tributação padrão
        $tributacaoStId = $validas->contains($empresa->entrada_tributacao_st_padrao_id)
            ? $empresa->entrada_tributacao_st_padrao_id
            : null;

        $produtos = app(ProdutoController::class);
        $margem = (float) $empresa->entrada_margem_padrao;
        $criados = [];
        $falhas = [];

        foreach (array_unique($dados['indices']) as $indice) {
            $item = $nfe['itens'][$indice] ?? null;
            if (! $item) {
                continue;
            }

            $ncm = $this->resolverNcm($item['ncm']);
            if (! $ncm) {
                $falhas[] = ['indice' => $indice, 'motivo' => "NCM {$item['ncm']} não cadastrado. Cadastre-o e use o botão de cadastro do item."];
                continue;
            }

            $custo = $item['quantidade'] > 0 ? round($item['valor_total'] / $item['quantidade'], 2) : 0;
            $unidade = strtoupper($item['unidade'] ?: 'UN');
            $tributacaoId = ($item['com_st'] && $tributacaoStId)
                ? $tributacaoStId
                : $empresa->entrada_tributacao_padrao_id;

            try {
                $produto = $produtos->criarAPartirDeDados([
                    'nome'               => mb_substr($item['descricao'], 0, 255),
                    'codigo_barras'      => $item['ean'],
                    'ncm_id'             => $ncm->id,
                    'tributacao_id'      => $tributacaoId,
                    'pis_cofins_id'      => $empresa->entrada_pis_cofins_padrao_id,
                    'unidade_comercial'  => $unidade,
                    'unidade_tributavel' => strtoupper($item['unidade_tributavel'] ?: $unidade),
                    'origem_mercadoria'  => (int) ($item['origem'] ?: 0),
                    'preco_custo'        => $custo,
                    'preco_venda'        => round($custo * (1 + $margem / 100), 2),
                    'estoque'            => 0,
                    'estoque_minimo'     => 0,
                    'produto_balanca'    => '0',
                    'tem_preco_atacado'  => '0',
                    'atacado_tem_prazo'  => '0',
                ]);
            } catch (ValidationException $e) {
                $falhas[] = ['indice' => $indice, 'motivo' => collect($e->errors())->flatten()->implode(' ')];
                continue;
            }

            $criados[] = [
                'indice'  => $indice,
                'produto' => $produto->only(['id', 'nome', 'codigo_interno', 'codigo_barras']),
            ];
        }

        return response()->json(compact('criados', 'falhas'));
    }

    public function analisarXml(Request $request, NfeXmlParser $parser)
    {
        $request->validate([
            'xml'                 => ['required', 'file', 'max:2048', 'extensions:xml'],
            'operacao_entrada_id' => ['required', Rule::exists('operacoes_entrada', 'id')->where('ativo', true)],
        ], [
            'xml.required'                 => 'Selecione um arquivo XML.',
            'xml.extensions'               => 'O arquivo deve ter extensão .xml.',
            'xml.max'                      => 'O XML excede 2 MB.',
            'operacao_entrada_id.required' => 'Selecione a operação da entrada.',
        ]);

        try {
            $nfe = $parser->parse($request->file('xml')->get());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $fornecedor = Fornecedor::where('cpf_cnpj', $nfe['fornecedor']['cpf_cnpj'])->first();

        if ($erro = $this->erroDuplicidadeXml($nfe, $fornecedor)) {
            return response()->json(['message' => $erro], 422);
        }

        $itens = [];
        $ncms = [];
        $operacao = OperacaoEntrada::findOrFail($request->input('operacao_entrada_id'));
        $conversao = new ConversaoFiscalEntrada($operacao);
        $codigosCfop = CfopEntrada::pluck('codigo', 'id');

        foreach ($nfe['itens'] as $item) {
            $achado = $this->localizarProduto($item, $fornecedor);

            if (! array_key_exists($item['ncm'], $ncms)) {
                $ncms[$item['ncm']] = $this->resolverNcm($item['ncm']);
            }
            $ncm = $ncms[$item['ncm']];
            $conv = $conversao->converter($item['cfop'] ?: null, $item['cst'] ?: null, $item['csosn'] ?: null);

            $itens[] = [
                'ean'                => $item['ean'],
                'codigo'             => $item['codigo'],
                'descricao'          => $item['descricao'],
                'unidade'            => $item['unidade'],
                'unidade_tributavel' => $item['unidade_tributavel'] ?: $item['unidade'],
                'ncm'                => $item['ncm'],
                'ncm_registro'       => $ncm?->only(['id', 'codigo', 'descricao']),
                'origem'             => (int) ($item['origem'] ?: 0),
                'valor_custo'        => $item['quantidade'] > 0 ? round($item['valor_total'] / $item['quantidade'], 2) : 0,
                'produto'            => $achado
                    ? $achado['produto']->only(['id', 'nome', 'codigo_interno', 'codigo_barras']) + ['por' => $achado['por']]
                    : null,
                'conversao' => [
                    'cfop_origem'  => $item['cfop'] ?: null,
                    'cfop_entrada' => $conv['cfop_entrada_id'] ? $codigosCfop->get($conv['cfop_entrada_id']) : null,
                    'tipo_origem'  => $item['csosn'] ? 'CSOSN' : 'CST',
                    'cst_origem'   => $item['csosn'] ?: ($item['cst'] ?: null),
                    'tipo_entrada' => $conversao->regime() === 'normal' ? 'CST' : 'CSOSN',
                    'cst_entrada'  => $conv['cst_csosn_entrada'],
                ],
            ];
        }

        $token = (string) Str::uuid();
        Cache::put('importar_xml:' . auth()->id() . ':' . $token, $nfe + ['operacao_entrada_id' => $operacao->id], now()->addMinutes(30));

        return response()->json([
            'token' => $token,
            'nota'  => [
                'operacao'             => $operacao->descricao,
                'numero'               => $nfe['numero'],
                'serie'                => $nfe['serie'],
                'fornecedor_nome'      => $nfe['fornecedor']['nome'],
                'fornecedor_documento' => $nfe['fornecedor']['cpf_cnpj'],
                'fornecedor_novo'      => $fornecedor === null,
            ],
            'itens' => $itens,
        ]);
    }

    private function resolverNcm(string $codigo): ?Ncm
    {
        $codigo = preg_replace('/\D/', '', $codigo);
        if (strlen($codigo) !== 8) {
            return null;
        }

        // aceita gravado como 12345678 ou 1234.56.78
        $formatado = substr($codigo, 0, 4) . '.' . substr($codigo, 4, 2) . '.' . substr($codigo, 6, 2);

        return Ncm::whereIn('codigo', [$codigo, $formatado])->first(['id', 'codigo', 'descricao']);
    }

public function confirmarImportacaoXml(Request $request)
    {
        $dados = $request->validate([
            'token'      => ['required', 'string'],
            'produtos'   => ['present', 'array'],   // [indice_do_item => produto_id]
            'produtos.*' => ['integer'],
        ]);

        $chaveCache = 'importar_xml:' . auth()->id() . ':' . $dados['token'];
        $nfe = Cache::get($chaveCache);

        if (! $nfe) {
            return response()->json(['message' => 'A importação expirou. Envie o XML novamente.'], 422);
        }

        $fornecedor = Fornecedor::where('cpf_cnpj', $nfe['fornecedor']['cpf_cnpj'])->first();

        if ($erro = $this->erroDuplicidadeXml($nfe, $fornecedor)) {
            return response()->json(['message' => $erro], 422);
        }

        $ids = array_values(array_unique($dados['produtos']));
        $produtos = Produto::ativos()->where('tem_variacao', false)->whereIn('id', $ids)->get()->keyBy('id');

        if ($produtos->count() !== count($ids)) {
            return response()->json(['message' => 'Algum produto assimilado está inativo ou possui variação.'], 422);
        }

        $avisos = [];

        try {
            $entrada = DB::transaction(function () use ($nfe, $fornecedor, $dados, $produtos, &$avisos) {
                $dadosFornecedor = $nfe['fornecedor'];

                if (! $fornecedor) {
                    $fornecedor = Fornecedor::create($dadosFornecedor + ['ativo' => true]);
                    $avisos[] = "Fornecedor \"{$fornecedor->nome}\" cadastrado automaticamente a partir do XML.";
                } elseif (! $fornecedor->ativo) {
                    $fornecedor->update(['ativo' => true]);
                    $avisos[] = "O fornecedor \"{$fornecedor->nome}\" estava inativo e foi reativado.";
                }

                $entrada = EntradaNota::create([
                    'operacao_entrada_id' => $nfe['operacao_entrada_id'],
                    'fornecedor_id'     => $fornecedor->id,
                    'user_id'           => auth()->id(),
                    'tipo_entrada'      => 'xml',
                    'status'            => 'rascunho',
                    'chave_acesso'      => $nfe['chave'],
                    'modelo'            => $nfe['modelo'],
                    'serie'             => $nfe['serie'],
                    'numero'            => $nfe['numero'],
                    'data_emissao'      => $nfe['data_emissao'],
                    'data_entrada'      => today()->toDateString(),
                    'natureza_operacao' => $nfe['natureza_operacao'],
                    'valor_frete'       => $nfe['valor_frete'],
                    'valor_desconto'    => 0, // o desconto do XML já está nos itens
                    'valor_outras'      => $nfe['valor_outras'],
                    'atualizar_custo'   => true,
                ]);

                $ignorados = [];

                foreach ($nfe['itens'] as $indice => $item) {
                    $produtoId = $dados['produtos'][$indice] ?? null;

                    if (! $produtoId) {
                        $ignorados[] = "{$item['codigo']} - {$item['descricao']}";
                        continue;
                    }

                    $produto = $produtos[$produtoId];

                    $entrada->itens()->create([
                        'produto_id'        => $produto->id,
                        'codigo_fornecedor' => $item['codigo'] ?: null,
                        'descricao'         => $produto->nome,
                        'unidade'           => $produto->unidade_comercial,
                        'quantidade'        => $item['quantidade'],
                        'valor_unitario'    => $item['valor_unitario'],
                        'valor_desconto'    => $item['valor_desconto'],
                        'valor_total'       => $item['valor_total'],
                        'lote'              => $item['lote'],
                        'validade'          => $item['validade'],
                        'cfop_origem'       => $item['cfop'] ?: null,
                        'cst_origem'        => $item['cst'] ?: null,
                        'csosn_origem'      => $item['csosn'] ?: null,
                        'origem_mercadoria' => $item['origem'] !== '' ? (int) $item['origem'] : null,
                    ]);
                }

                if ($ignorados) {
                    $avisos[] = count($ignorados) . ' item(ns) ficaram de fora por não terem produto assimilado: ' . implode('; ', $ignorados);
                }

                $entrada->recalcularTotais();
                $this->aplicarConversaoFiscal($entrada);

                $pendentes = $entrada->itens->whereNull('cfop_entrada_id')->count();
                if ($pendentes) {
                    $avisos[] = "{$pendentes} item(ns) com conversão de CFOP pendente (o CFOP do fornecedor não tem regra para esta operação).";
                }

                return $entrada;
            });
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Falha ao criar o rascunho: ' . $e->getMessage()], 500);
        }

        Cache::forget($chaveCache);

        session()->flash('sucesso', 'XML importado: ' . $entrada->itens()->count() . ' de ' . count($nfe['itens']) . ' itens no rascunho.');
        session()->flash('avisos', $avisos);

        return response()->json(['redirect' => route('entradas-nota.edit', $entrada)]);
    }

    private function erroDuplicidadeXml(array $nfe, ?Fornecedor $fornecedor): ?string
    {
        if (EntradaNota::where('chave_acesso', $nfe['chave'])->exists()) {
            return 'Esta NF-e já foi lançada (chave de acesso já cadastrada).';
        }

        if ($fornecedor && EntradaNota::where('fornecedor_id', $fornecedor->id)
                ->where('modelo', '55')
                ->where('serie', $nfe['serie'])
                ->where('numero', $nfe['numero'])
                ->where('status', '!=', 'cancelada')
                ->exists()) {
            return 'Esta nota já foi lançada para este fornecedor.';
        }

        return null;
    }

    /** Retorna ['produto' => Produto, 'por' => texto] ou null. */
    private function localizarProduto(array $item, ?Fornecedor $fornecedor = null): ?array
    {
        $base = Produto::ativos()->where('tem_variacao', false);

        if ($item['ean'] !== '') {
            $p = (clone $base)->where('codigo_barras', $item['ean'])->first();
            if ($p) {
                return ['produto' => $p, 'por' => "ean: {$item['ean']}"];
            }
        }

        if ($item['codigo'] !== '') {
            $p = (clone $base)->where('referencia', $item['codigo'])->first();
            if ($p) {
                return ['produto' => $p, 'por' => "referência: {$item['codigo']}"];
            }

            // Vínculo de uma importação anterior: mesmo fornecedor + mesmo código do fornecedor
            if ($fornecedor) {
                $produtoId = EntradaNotaItem::where('codigo_fornecedor', $item['codigo'])
                    ->whereHas('entrada', fn ($q) => $q->where('fornecedor_id', $fornecedor->id))
                    ->latest('id')
                    ->value('produto_id');

                $p = $produtoId ? (clone $base)->find($produtoId) : null;
                if ($p) {
                    return ['produto' => $p, 'por' => 'vínculo de importação anterior'];
                }
            }
        }

        return null;
    }

    public function create()
    {
        $entrada = new EntradaNota([
            'operacao_entrada_id' => OperacaoEntrada::where('codigo', 'compra_comercializacao')->value('id'),
            'modelo'              => '55',
            'data_entrada'        => today()->toDateString(),
            'atualizar_custo'     => true,
        ]);

        return view('notasfiscais.entrada.create', $this->dadosFormulario($entrada));
    }

    public function store(Request $request)
    {
        $entrada = $this->salvar($request, null);

        if ($request->input('acao') === 'finalizar') {
            $this->finalizar($entrada);

            return redirect()->route('entradas-nota.index')
                ->with('sucesso', 'Entrada finalizada e estoque atualizado.');
        }

        return redirect()->route('entradas-nota.edit', $entrada)
            ->with('sucesso', 'Rascunho da entrada salvo.');
    }

    public function edit(EntradaNota $entrada)
    {
        return view('notasfiscais.entrada.edit', $this->dadosFormulario($entrada));
    }

    public function update(Request $request, EntradaNota $entrada)
    {
        abort_unless($entrada->isRascunho(), 403, 'Esta entrada já foi finalizada e não pode ser alterada.');

        $entrada = $this->salvar($request, $entrada);

        if ($request->input('acao') === 'finalizar') {
            $this->finalizar($entrada);

            return redirect()->route('entradas-nota.index')
                ->with('sucesso', 'Entrada finalizada e estoque atualizado.');
        }

        return redirect()->route('entradas-nota.edit', $entrada)
            ->with('sucesso', 'Rascunho da entrada salvo.');
    }

    public function destroy(EntradaNota $entrada)
    {
        abort_unless($entrada->isRascunho(), 403, 'Só é possível excluir entradas em rascunho.');

        $entrada->delete(); // itens caem por cascade

        return redirect()->route('entradas-nota.index')
            ->with('sucesso', 'Rascunho excluído.');
    }

    /**
     * Busca de produtos para o formulário (JSON).
     * Nesta etapa só produtos SEM variação; variações entram quando enviarmos o ProdutoVariante.
     */
    public function buscarProdutos(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $produtos = Produto::ativos()
            ->where('tem_variacao', false)
            ->where(function ($w) use ($q) {
                $w->where('nome', 'like', "%{$q}%")
                  ->orWhere('referencia', 'like', "%{$q}%")
                  ->orWhere('codigo_barras', $q)
                  ->orWhere('codigo_interno', $q);
            })
            ->orderBy('nome')
            ->limit(15)
            ->get(['id', 'nome', 'codigo_interno', 'codigo_barras', 'unidade_comercial', 'preco_custo', 'estoque']);

        return response()->json($produtos);
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    private function dadosFormulario(EntradaNota $entrada): array
    {
        $itens = old('itens') ?? $entrada->itens
            ->map(fn ($i) => [
                'produto_id'        => $i->produto_id,
                'descricao'         => $i->descricao,
                'codigo_fornecedor' => $i->codigo_fornecedor,
                'quantidade'        => (float) $i->quantidade,
                'valor_unitario'    => (float) $i->valor_unitario,
                'valor_desconto'    => (float) $i->valor_desconto,
                'lote'              => $i->lote,
                'validade'          => $i->validade?->format('Y-m-d'),
            ])
            ->values()
            ->all();

        return [
            'entrada'         => $entrada,
            'fornecedores'    => Fornecedor::ativos()->orderBy('nome')->get(),
            'itens'           => $itens,
            'somenteLeitura'  => $entrada->exists && ! $entrada->isRascunho(),
            'operacoes'       => OperacaoEntrada::where('ativo', true)->orderBy('ordem')->get(),
        ];
    }

    private function validar(Request $request, ?EntradaNota $entrada): array
    {
        $dados = $request->validate([
            'operacao_entrada_id' => ['required', Rule::exists('operacoes_entrada', 'id')->where('ativo', true)],
            'fornecedor_id'       => ['required', 'exists:fornecedores,id'],
            'modelo'              => ['required', Rule::in(['55', '01'])],
            'serie'               => ['nullable', 'string', 'max:3'],
            'numero'              => ['required', 'string', 'max:9'],
            'chave_acesso'        => [
                'nullable', 'digits:44',
                Rule::unique('entradas_nota', 'chave_acesso')->ignore($entrada?->id),
            ],
            'data_emissao'      => ['required', 'date'],
            'data_entrada'      => ['required', 'date', 'after_or_equal:data_emissao'],
            'natureza_operacao' => ['nullable', 'string', 'max:60'],
            'valor_frete'       => ['nullable', 'numeric', 'min:0'],
            'valor_desconto'    => ['nullable', 'numeric', 'min:0'],
            'valor_outras'      => ['nullable', 'numeric', 'min:0'],
            'observacao'        => ['nullable', 'string', 'max:1000'],

            'itens'                     => ['required', 'array', 'min:1'],
            'itens.*.produto_id'        => ['required', 'exists:produtos,id'],
            'itens.*.descricao'         => ['nullable', 'string', 'max:200'],
            'itens.*.codigo_fornecedor' => ['nullable', 'string', 'max:60'],
            'itens.*.quantidade'        => ['required', 'numeric', 'gt:0'],
            'itens.*.valor_unitario'    => ['required', 'numeric', 'min:0'],
            'itens.*.valor_desconto'    => ['nullable', 'numeric', 'min:0'],
            'itens.*.lote'              => ['nullable', 'string', 'max:30'],
            'itens.*.validade'          => ['nullable', 'date'],
        ], [
            'operacao_entrada_id.required' => 'Selecione a operação (CFOP) da entrada.',
            'itens.required'       => 'Adicione ao menos um item à entrada.',
            'itens.min'            => 'Adicione ao menos um item à entrada.',
            'chave_acesso.digits'  => 'A chave de acesso deve ter 44 dígitos.',
            'chave_acesso.unique'  => 'Já existe uma entrada com esta chave de acesso.',
        ]);

        // Nota duplicada (mesmo fornecedor + modelo + série + número)
        $duplicada = EntradaNota::where('fornecedor_id', $dados['fornecedor_id'])
            ->where('modelo', $dados['modelo'])
            ->where('serie', $dados['serie'] ?? null)
            ->where('numero', $dados['numero'])
            ->where('status', '!=', 'cancelada')
            ->when($entrada, fn ($q) => $q->where('id', '!=', $entrada->id))
            ->exists();

        if ($duplicada) {
            throw ValidationException::withMessages([
                'numero' => 'Esta nota já foi lançada para este fornecedor.',
            ]);
        }

        return $dados;
    }

    private function salvar(Request $request, ?EntradaNota $entrada): EntradaNota
    {
        $dados = $this->validar($request, $entrada);

        $produtos = Produto::whereIn('id', collect($dados['itens'])->pluck('produto_id')->unique())
            ->get()
            ->keyBy('id');

        foreach ($dados['itens'] as $i => $item) {
            if ($produtos[$item['produto_id']]->tem_variacao) {
                throw ValidationException::withMessages([
                    "itens.{$i}.produto_id" => 'Produtos com variação ainda não são suportados na entrada de nota.',
                ]);
            }
        }

        return DB::transaction(function () use ($request, $dados, $entrada, $produtos) {
            $entrada ??= new EntradaNota([
                'status'       => 'rascunho',
                'tipo_entrada' => 'manual',
                'user_id'      => auth()->id(),
            ]);

            $entrada->fill(Arr::except($dados, ['itens']));
            $entrada->valor_frete     = $dados['valor_frete'] ?? 0;
            $entrada->valor_desconto  = $dados['valor_desconto'] ?? 0;
            $entrada->valor_outras    = $dados['valor_outras'] ?? 0;
            $entrada->atualizar_custo = $request->boolean('atualizar_custo');
            $entrada->save();

            $fiscaisAnteriores = $entrada->itens->mapWithKeys(fn ($i) => [
                $i->produto_id . '|' . $i->codigo_fornecedor
                    => $i->only(['cfop_origem', 'cst_origem', 'csosn_origem', 'origem_mercadoria']),
            ]);

            // Rascunho: recria os itens a cada gravação
            $entrada->itens()->delete();

            foreach ($dados['itens'] as $item) {
                $produto  = $produtos[$item['produto_id']];
                $qtd      = (float) $item['quantidade'];
                $unit     = (float) $item['valor_unitario'];
                $desconto = (float) ($item['valor_desconto'] ?? 0);
                $fiscal = $fiscaisAnteriores->get($produto->id . '|' . ($item['codigo_fornecedor'] ?? ''), []);

                $entrada->itens()->create([
                    'produto_id'        => $produto->id,
                    'codigo_fornecedor' => $item['codigo_fornecedor'] ?? null,
                    'descricao'         => $produto->nome,
                    'unidade'           => $produto->unidade_comercial,
                    'quantidade'        => $qtd,
                    'valor_unitario'    => $unit,
                    'valor_desconto'    => $desconto,
                    'valor_total'       => round(($qtd * $unit) - $desconto, 2),
                    'lote'              => $item['lote'] ?? null,
                    'validade'          => $item['validade'] ?? null,
                    ...$fiscal,
                ]);
            }

            $entrada->recalcularTotais();
            $this->aplicarConversaoFiscal($entrada);

            return $entrada;
        });
    }

    private function aplicarConversaoFiscal(EntradaNota $entrada): void
    {
        $entrada->load(['operacao', 'fornecedor', 'itens']);

        if (! $entrada->operacao) {
            return;
        }

        $conversao = new ConversaoFiscalEntrada($entrada->operacao);
        $presumido = $conversao->cfopOrigemPresumido($entrada->fornecedor);

        foreach ($entrada->itens as $item) {
            $item->update($conversao->converter(
                $item->cfop_origem ?: $presumido,
                $item->cst_origem,
                $item->csosn_origem
            ));
        }
    }

    /**
     * Aplica a entrada: soma estoque, atualiza custo (opcional) e gera lotes.
     */
    private function finalizar(EntradaNota $entrada): void
    {
        DB::transaction(function () use ($entrada) {
            $entrada = EntradaNota::whereKey($entrada->id)->lockForUpdate()->firstOrFail();

            if (! $entrada->isRascunho()) {
                return; // proteção contra duplo clique / reenvio
            }
            $movimenta = $entrada->operacao?->movimenta_estoque ?? true;

            foreach ($movimenta ? $entrada->itens : [] as $item) {
                $produto = Produto::whereKey($item->produto_id)->lockForUpdate()->firstOrFail();

                $produto->increment('estoque', $item->quantidade);

                if ($entrada->atualizar_custo && (float) $item->quantidade > 0) {
                    // custo líquido unitário (já descontado o desconto do item)
                    $produto->update([
                        'preco_custo' => round((float) $item->valor_total / (float) $item->quantidade, 2),
                    ]);
                }

                if ($item->lote || $item->validade) {
                    ProdutoLote::create([
                        'produto_id'           => $item->produto_id,
                        'entrada_nota_item_id' => $item->id,
                        'lote'                 => $item->lote,
                        'validade'             => $item->validade,
                        'quantidade_inicial'   => $item->quantidade,
                        'quantidade_atual'     => $item->quantidade,
                    ]);
                }
            }

            $entrada->update([
                'status'        => 'finalizada',
                'finalizada_em' => now(),
            ]);
        });
    }
}