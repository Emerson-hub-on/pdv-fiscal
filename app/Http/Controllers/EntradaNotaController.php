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
use App\Models\CfopEntradaConversao;
use App\Models\FormaPagamento;
use App\Models\CstEntradaConversao;
use App\Services\ContaPagarService;
use App\Services\EstoqueService;
use App\Support\XmlDuplicatas;


class EntradaNotaController extends Controller
{
    /** Diferença máxima (R$) aceita entre o total da entrada e o total do XML. */
    private const TOLERANCIA_TOTAL = 0.01;

    private const CSOSN = [
        '101' => 'Tributada pelo Simples Nacional com permissão de crédito',
        '102' => 'Tributada pelo Simples Nacional sem permissão de crédito',
        '103' => 'Isenção do ICMS no Simples Nacional para faixa de receita bruta',
        '201' => 'Tributada pelo SN com permissão de crédito e com cobrança do ICMS por ST',
        '202' => 'Tributada pelo SN sem permissão de crédito e com cobrança do ICMS por ST',
        '203' => 'Isenção do ICMS no SN para faixa de receita bruta e com cobrança do ICMS por ST',
        '300' => 'Imune',
        '400' => 'Não tributada pelo Simples Nacional',
        '500' => 'ICMS cobrado anteriormente por ST (substituído) ou por antecipação',
        '900' => 'Outros',
    ];

    private const CST = [
        '00' => 'Tributada integralmente',
        '10' => 'Tributada e com cobrança do ICMS por ST',
        '20' => 'Com redução de base de cálculo',
        '30' => 'Isenta ou não tributada e com cobrança do ICMS por ST',
        '40' => 'Isenta',
        '41' => 'Não tributada',
        '50' => 'Suspensão',
        '51' => 'Diferimento',
        '60' => 'ICMS cobrado anteriormente por ST',
        '70' => 'Com redução de base de cálculo e cobrança do ICMS por ST',
        '90' => 'Outras',
    ];
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
                'quantidade'         => $item['quantidade'],
                'total_item'         => round((float) $item['valor_total'] + (float) ($item['valor_frete'] ?? 0) + (float) ($item['valor_outras_despesas'] ?? 0), 2),
                'produto'            => $achado
                    ? $this->produtoParaConferencia($achado['produto']) + ['por' => $achado['por']]
                    : null,
                // variação já identificada (EAN/vínculo): recebe a quantidade inteira; senão o operador distribui
                'distribuicao'       => ($achado['variante'] ?? null)
                    ? [['variante_id' => $achado['variante']->id, 'quantidade' => $item['quantidade']]]
                    : [],
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

        // prévia do total: o que sobra de frete/outras despesas no cabeçalho entra na entrada junto com os itens
        $freteItens  = round(collect($nfe['itens'])->sum(fn ($i) => (float) ($i['valor_frete'] ?? 0)), 2);
        $outrasItens = round(collect($nfe['itens'])->sum(fn ($i) => (float) ($i['valor_outras_despesas'] ?? 0)), 2);
        $restoCabecalho = max(round($nfe['valor_frete'] - $freteItens, 2), 0) + max(round($nfe['valor_outras'] - $outrasItens, 2), 0);

        $token = (string) Str::uuid();
        Cache::put('importar_xml:' . auth()->id() . ':' . $token, $nfe + ['operacao_entrada_id' => $operacao->id], now()->addMinutes(30));

        return response()->json([
            'token' => $token,
            'nota'  => [
                'operacao'             => $operacao->descricao,
                'numero'               => $nfe['numero'],
                'total_xml'            => $this->totalComparavelXml($nfe),
                'resto_cabecalho'      => round($restoCabecalho, 2),
                'serie'                => $nfe['serie'],
                'fornecedor_nome'      => $nfe['fornecedor']['nome'],
                'fornecedor_documento' => $nfe['fornecedor']['cpf_cnpj'],
                'fornecedor_novo'      => $fornecedor === null,
                'pagamento' => ($f = $this->formaDoXml($nfe['pagamento'] ?? null))
                    ? $f->descricao . ' (' . ($f->ind_pag ? 'a prazo' : 'à vista') . ')'
                    : null,
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
            // [indice_do_item => [['variante_id' => x, 'quantidade' => n], ...]] — só para produtos com variação
            'distribuicoes'                 => ['nullable', 'array'],
            'distribuicoes.*'               => ['array'],
            'distribuicoes.*.*.variante_id' => ['required', 'integer'],
            'distribuicoes.*.*.quantidade'  => ['required', 'numeric', 'gt:0'],
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
        $produtos = Produto::ativos()->with('variantes')->whereIn('id', $ids)->get()->keyBy('id');

        if ($produtos->count() !== count($ids)) {
            return response()->json(['message' => 'Algum produto assimilado está inativo ou não existe mais.'], 422);
        }

        // Plano de itens: indice => [[ProdutoVariante|null, quantidade], ...]
        $plano = [];

        foreach ($dados['produtos'] as $indice => $produtoId) {
            $item = $nfe['itens'][$indice] ?? null;
            if (! $item) {
                continue;
            }

            $produto = $produtos[$produtoId];
            $n = (int) $indice + 1;

            if (! $produto->tem_variacao) {
                $plano[$indice] = [[null, (float) $item['quantidade']]];
                continue;
            }

            $partes = collect($dados['distribuicoes'][$indice] ?? [])
                ->groupBy('variante_id')
                ->map(fn ($g) => (float) $g->sum('quantidade'));

            foreach ($partes as $varId => $qtd) {
                $variante = $produto->variantes->firstWhere('id', (int) $varId);

                if (! $variante) {
                    return response()->json(['message' => "Item {$n}: variação inválida para o produto \"{$produto->nome}\"."], 422);
                }
                if (fmod($qtd, 1.0) != 0.0) {
                    return response()->json(['message' => "Item {$n}: a quantidade de cada variação deve ser inteira (o estoque da variação é inteiro)."], 422);
                }

                $plano[$indice][] = [$variante, $qtd];
            }

            if (abs($partes->sum() - (float) $item['quantidade']) > 0.0005) {
                return response()->json([
                    'message' => "Item {$n} ({$item['descricao']}): a distribuição entre as variações soma {$partes->sum()}, mas a nota tem {$item['quantidade']}.",
                ], 422);
            }
        }

        $avisos = [];

        try {
            $entrada = DB::transaction(function () use ($nfe, $fornecedor, $dados, $produtos, $plano, &$avisos) {
                $dadosFornecedor = $nfe['fornecedor'];

                if (! $fornecedor) {
                    $fornecedor = Fornecedor::create($dadosFornecedor + ['ativo' => true]);
                    $avisos[] = "Fornecedor \"{$fornecedor->nome}\" cadastrado automaticamente a partir do XML.";
                } elseif (! $fornecedor->ativo) {
                    $fornecedor->update(['ativo' => true]);
                    $avisos[] = "O fornecedor \"{$fornecedor->nome}\" estava inativo e foi reativado.";
                }

                $forma = $this->formaDoXml($nfe['pagamento'] ?? null);

                if (! $forma && ! empty($nfe['pagamento']['meio'])) {
                    $avisos[] = 'O meio de pagamento do XML (código ' . $nfe['pagamento']['meio']
                        . ') não está cadastrado em Formas de pagamento. Escolha a forma manualmente.';
                }

                if (($nfe['pagamento']['qtd'] ?? 0) > 1) {
                    $avisos[] = 'O XML informa ' . $nfe['pagamento']['qtd'] . ' formas de pagamento; foi usada a de maior valor.';
                }

                // frete/outras despesas já informados item a item não podem ser contados de novo no cabeçalho
                $freteItens  = round(collect($nfe['itens'])->sum(fn ($i) => (float) ($i['valor_frete'] ?? 0)), 2);
                $outrasItens = round(collect($nfe['itens'])->sum(fn ($i) => (float) ($i['valor_outras_despesas'] ?? 0)), 2);

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
                    'forma_pagamento_id'=> $forma?->id,
                    'natureza_operacao' => Str::limit(
                        (string) OperacaoEntrada::whereKey($nfe['operacao_entrada_id'])->value('descricao'),
                        60,
                        ''
                    ),
                    'valor_frete'       => max(round($nfe['valor_frete'] - $freteItens, 2), 0),
                    'valor_desconto'    => 0, // o desconto do XML já está nos itens
                    'valor_outras'      => max(round($nfe['valor_outras'] - $outrasItens, 2), 0),
                    'valor_total_xml'   => $this->totalComparavelXml($nfe),
                    'atualizar_custo'   => true,
                ]);

                // duplicatas do XML (Contas a Pagar): gravadas direto na tabela, sem depender de $fillable/$casts do model
                $duplicatas = XmlDuplicatas::normalizar($nfe['duplicatas'] ?? []);
                if ($duplicatas) {
                    DB::table($entrada->getTable())->where('id', $entrada->id)
                        ->update(['duplicatas' => json_encode($duplicatas)]);
                }

                $ignorados = [];

                foreach ($nfe['itens'] as $indice => $item) {
                    if (! isset($plano[$indice])) {
                        $ignorados[] = "{$item['codigo']} - {$item['descricao']}";
                        continue;
                    }

                    $produto  = $produtos[$dados['produtos'][$indice]];
                    $partes   = $plano[$indice];
                    $qtdTotal = (float) $item['quantidade'];
                    $acc      = array_fill_keys(
                        ['valor_desconto', 'valor_total', 'valor_outras_despesas', 'valor_frete', 'bc_icms', 'valor_icms', 'valor_ipi'],
                        0.0
                    );

                    // sem variação: uma parte só (fator 1); com variação: uma linha por variante, valores rateados pela quantidade
                    foreach ($partes as $n => [$variante, $qtd]) {
                        $ultima = $n === count($partes) - 1;
                        $fator  = $qtdTotal > 0 ? $qtd / $qtdTotal : 1;

                        $entrada->itens()->create([
                            'produto_id'            => $produto->id,
                            'produto_variante_id'   => $variante?->id,
                            'codigo_fornecedor'     => $item['codigo'] ?: null,
                            'descricao'             => $this->descricaoItem($produto, $variante),
                            'unidade'               => $produto->unidade_comercial,
                            'quantidade'            => $qtd,
                            'valor_unitario'        => $item['valor_unitario'],
                            'valor_desconto'        => $this->ratear((float) $item['valor_desconto'], $fator, $ultima, $acc['valor_desconto']),
                            'valor_total'           => $this->ratear((float) $item['valor_total'], $fator, $ultima, $acc['valor_total']),
                            'valor_outras_despesas' => $this->ratear((float) ($item['valor_outras_despesas'] ?? 0), $fator, $ultima, $acc['valor_outras_despesas']),
                            'valor_frete'           => $this->ratear((float) ($item['valor_frete'] ?? 0), $fator, $ultima, $acc['valor_frete']),
                            'bc_icms'               => $this->ratear((float) ($item['bc_icms'] ?? 0), $fator, $ultima, $acc['bc_icms']),
                            'valor_icms'            => $this->ratear((float) ($item['valor_icms'] ?? 0), $fator, $ultima, $acc['valor_icms']),
                            'aliquota_icms'         => $item['aliquota_icms'] ?? 0,
                            'valor_ipi'             => $this->ratear((float) ($item['valor_ipi'] ?? 0), $fator, $ultima, $acc['valor_ipi']),
                            'aliquota_ipi'          => $item['aliquota_ipi'] ?? 0,
                            'lote'                  => $item['lote'],
                            'validade'              => $item['validade'],
                            'cfop_origem'           => $item['cfop'] ?: null,
                            'cst_origem'            => $item['cst'] ?: null,
                            'csosn_origem'          => $item['csosn'] ?: null,
                            'origem_mercadoria'     => $item['origem'] !== '' ? (int) $item['origem'] : null,
                        ]);
                    }
                }

                if ($ignorados) {
                    $avisos[] = count($ignorados) . ' item(ns) ficaram de fora por não terem produto assimilado: ' . implode('; ', $ignorados);
                }

                $entrada->recalcularTotais();

                // trava: o total da entrada precisa bater com o total do XML (nada ficou de fora nem foi rateado errado)
                if (($dif = $this->diferencaTotalXml($entrada)) !== null && abs($dif) > self::TOLERANCIA_TOTAL) {
                    throw new \DomainException($this->mensagemDiferencaTotal($entrada, $dif));
                }

                $this->aplicarConversaoFiscal($entrada);

                $pendentes = $entrada->itens->whereNull('cfop_entrada_id')->count();
                if ($pendentes) {
                    $avisos[] = "{$pendentes} item(ns) com conversão de CFOP pendente (o CFOP do fornecedor não tem regra para esta operação).";
                }

                return $entrada;
            });
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Falha ao criar o rascunho: ' . $e->getMessage()], 500);
        }

        Cache::forget($chaveCache);

        session()->flash('sucesso', 'XML importado: ' . count($plano) . ' de ' . count($nfe['itens']) . ' itens da nota no rascunho.');
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

    /** Retorna ['produto' => Produto, 'variante' => ProdutoVariante|null, 'por' => texto] ou null. */
    private function localizarProduto(array $item, ?Fornecedor $fornecedor = null): ?array
    {
        $base = Produto::ativos();

        if ($item['ean'] !== '') {
            // EAN de uma variação: já define produto e variante
            $variante = ProdutoVariante::where('codigo_barras', $item['ean'])
                ->whereHas('produto', fn ($q) => $q->ativos())
                ->with('produto')
                ->first();
            if ($variante) {
                return ['produto' => $variante->produto, 'variante' => $variante, 'por' => "ean da variação: {$item['ean']}"];
            }

            $p = (clone $base)->where('codigo_barras', $item['ean'])->first();
            if ($p) {
                return ['produto' => $p, 'variante' => null, 'por' => "ean: {$item['ean']}"];
            }
        }

        if ($item['codigo'] !== '') {
            $p = (clone $base)->where('referencia', $item['codigo'])->first();
            if ($p) {
                return ['produto' => $p, 'variante' => null, 'por' => "referência: {$item['codigo']}"];
            }

            // Vínculo de uma importação anterior: mesmo fornecedor + mesmo código do fornecedor
            if ($fornecedor) {
                $ultimo = EntradaNotaItem::where('codigo_fornecedor', $item['codigo'])
                    ->whereHas('entrada', fn ($q) => $q->where('fornecedor_id', $fornecedor->id))
                    ->latest('id')
                    ->first(['id', 'entrada_nota_id', 'produto_id']);

                $p = $ultimo ? (clone $base)->find($ultimo->produto_id) : null;
                if ($p) {
                    // só assume a variante se, naquela nota, esse código foi para UMA única variação
                    $variantes = EntradaNotaItem::where('entrada_nota_id', $ultimo->entrada_nota_id)
                        ->where('codigo_fornecedor', $item['codigo'])
                        ->where('produto_id', $p->id)
                        ->pluck('produto_variante_id')
                        ->unique()
                        ->values();

                    $variante = ($p->tem_variacao && $variantes->count() === 1 && $variantes->first())
                        ? ProdutoVariante::where('produto_id', $p->id)->find($variantes->first())
                        : null;

                    return ['produto' => $p, 'variante' => $variante, 'por' => 'vínculo de importação anterior'];
                }
            }
        }

        return null;
    }

    /** Dados do produto enviados à conferência do XML (com as variantes, quando houver). */
    private function produtoParaConferencia(Produto $produto): array
    {
        $dados = $produto->only(['id', 'nome', 'codigo_interno', 'codigo_barras']);
        $dados['tem_variacao'] = (bool) $produto->tem_variacao;

        if ($produto->tem_variacao) {
            $dados['variantes'] = $produto->variantes()
                ->orderBy('cor')->orderBy('tamanho')
                ->get(['id', 'cor', 'tamanho', 'codigo_barras', 'estoque'])
                ->all();
        }

        return $dados;
    }

    /** Total do XML na mesma base do total da entrada: produtos − desconto + frete + outras despesas. */
    private function totalComparavelXml(array $nfe): ?float
    {
        if (! isset($nfe['valor_produtos'])) {
            return null; // importação analisada antes desta regra existir
        }

        return round($nfe['valor_produtos'] - $nfe['valor_desconto'] + $nfe['valor_frete'] + $nfe['valor_outras'], 2);
    }

    /** Total da entrada − total do XML; null quando a entrada não veio de XML. */
    private function diferencaTotalXml(EntradaNota $entrada): ?float
    {
        if ($entrada->valor_total_xml === null) {
            return null;
        }

        return round((float) $entrada->valor_total - (float) $entrada->valor_total_xml, 2);
    }

    private function mensagemDiferencaTotal(EntradaNota $entrada, float $dif): string
    {
        $fmt = fn ($v) => 'R$ ' . number_format($v, 2, ',', '.');

        return 'O total da entrada (' . $fmt((float) $entrada->valor_total) . ') difere do total do XML ('
            . $fmt((float) $entrada->valor_total_xml) . ') em ' . $fmt(abs($dif)) . '. '
            . 'Confira se todos os itens foram assimilados e distribuídos corretamente.';
    }

    /** Impede finalizar uma entrada de XML cujo total não bate mais com o do XML. */
    private function exigirTotalIgualAoXml(EntradaNota $entrada): void
    {
        $dif = $this->diferencaTotalXml($entrada);

        if ($dif !== null && abs($dif) > self::TOLERANCIA_TOTAL) {
            throw ValidationException::withMessages(['itens' => $this->mensagemDiferencaTotal($entrada, $dif)]);
        }
    }

    private function descricaoItem(Produto $produto, ?ProdutoVariante $variante): string
    {
        $texto = $variante ? $produto->nome . ' - ' . $variante->rotulo() : $produto->nome;

        return Str::limit(trim($texto), 200, '');
    }

    /** Rateia um valor do item entre as variações; a última parte absorve a diferença de arredondamento. */
    private function ratear(float $total, float $fator, bool $ultima, float &$acumulado): float
    {
        $parte = $ultima ? round($total - $acumulado, 2) : round($total * $fator, 2);
        $acumulado = round($acumulado + $parte, 2);

        return $parte;
    }

    public function create()
    {
        $entrada = new EntradaNota([
            'modelo'              => '55',
            'data_entrada'        => today()->toDateString(),
            'atualizar_custo'     => true,
        ]);

        return view('notasfiscais.entrada.create', $this->dadosFormulario($entrada));
    }

    public function store(Request $request)
    {
        $parcelasCp = $this->parcelasContasPagar($request); // valida antes de gravar qualquer coisa
        $entrada = $this->salvar($request, null);

        if ($request->input('acao') === 'finalizar') {
            $this->exigirTotalIgualAoXml($entrada);
            $this->finalizar($entrada, $parcelasCp);

            return redirect()->route('entradas-nota.index')
                ->with('sucesso', $this->mensagemFinalizada($parcelasCp));
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

        $parcelasCp = $this->parcelasContasPagar($request); // valida antes de gravar qualquer coisa
        $entrada = $this->salvar($request, $entrada);

        if ($request->input('acao') === 'finalizar') {
            $this->exigirTotalIgualAoXml($entrada);
            $this->finalizar($entrada, $parcelasCp);

            return redirect()->route('entradas-nota.index')
                ->with('sucesso', $this->mensagemFinalizada($parcelasCp));
        }

        return redirect()->route('entradas-nota.edit', $entrada)
            ->with('sucesso', 'Rascunho da entrada salvo.');
    }

    public function destroy(EntradaNota $entrada)
    {
        $eraFinalizada = $entrada->isFinalizada();

        try {
            DB::transaction(function () use ($entrada) {
                if ($entrada->isFinalizada()) {
                    $this->estornar($entrada); // devolve a nota a rascunho e reverte o estoque
                }

                $entrada->delete(); // itens caem por cascade
            });
        } catch (\RuntimeException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect()->route('entradas-nota.index')
            ->with('sucesso', $eraFinalizada ? 'Entrada excluída e estoque estornado.' : 'Rascunho excluído.');
    }

    /**
     * Reabre uma entrada finalizada: estorna o estoque e volta a nota para rascunho,
     * liberando a edição normal (que depois pode ser finalizada de novo).
     */
    public function reabrir(EntradaNota $entrada)
    {
        abort_unless($entrada->isFinalizada(), 403, 'Só é possível reabrir entradas finalizadas.');

        try {
            $this->estornar($entrada);
        } catch (\RuntimeException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect()->route('entradas-nota.edit', $entrada)
            ->with('sucesso', 'Entrada reaberta e estoque estornado. Edite e finalize novamente.');
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

        // ?variacoes=1 (importação de XML): inclui produtos com variação e suas variantes
        $comVariacoes = $request->boolean('variacoes');

        $produtos = Produto::ativos()
            ->when(! $comVariacoes, fn ($w) => $w->where('tem_variacao', false))
            ->where(function ($w) use ($q, $comVariacoes) {
                $w->where('nome', 'like', "%{$q}%")
                  ->orWhere('referencia', 'like', "%{$q}%")
                  ->orWhere('codigo_barras', $q)
                  ->orWhere('codigo_interno', $q);

                if ($comVariacoes) {
                    $w->orWhereHas('variantes', fn ($v) => $v->where('codigo_barras', $q));
                }
            })
            ->when($comVariacoes, fn ($w) => $w->with('variantes:id,produto_id,cor,tamanho,codigo_barras,estoque'))
            ->orderBy('nome')
            ->limit(15)
            ->get(['id', 'nome', 'codigo_interno', 'codigo_barras', 'unidade_comercial', 'preco_custo', 'estoque', 'tem_variacao']);

        // produto com variação: o estoque mora nas variantes
        $produtos->each(function ($p) {
            if ($p->tem_variacao && $p->relationLoaded('variantes')) {
                $p->estoque = (int) $p->variantes->sum('estoque');
            }
        });

        return response()->json($produtos);
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    private function dadosFormulario(EntradaNota $entrada): array
    {
        $entrada->loadMissing(['itens.cfopEntrada', 'itens.produto', 'itens.variante']);
        $itens = old('itens') ?? $entrada->itens
            ->map(fn ($i) => [
                'produto_id'        => $i->produto_id,
                'produto_variante_id' => $i->produto_variante_id,
                'codigo'            => $i->produto?->codigo_interno,
                'codigo_barras'     => $i->variante?->codigo_barras ?: $i->produto?->codigo_barras,
                'descricao'         => $i->descricao,
                'codigo_fornecedor' => $i->codigo_fornecedor,
                'quantidade'        => (float) $i->quantidade,
                'qtd_por_caixa'     => (int) ($i->qtd_por_caixa ?: 1),
                'valor_unitario'    => (float) $i->valor_unitario,
                'valor_desconto'    => (float) $i->valor_desconto,
                'valor_outras_despesas' => (float) $i->valor_outras_despesas,
                'valor_frete'           => (float) $i->valor_frete,
                'bc_icms'               => (float) $i->bc_icms,
                'valor_icms'            => (float) $i->valor_icms,
                'aliquota_icms'         => (float) $i->aliquota_icms,
                'valor_ipi'             => (float) $i->valor_ipi,
                'aliquota_ipi'          => (float) $i->aliquota_ipi,
                'lote'              => $i->lote,
                'validade'          => $i->validade?->format('Y-m-d'),
                'cfop_origem'       => $i->cfop_origem,
                'cst_origem'        => $i->cst_origem,
                'csosn_origem'      => $i->csosn_origem,
                'origem_mercadoria' => $i->origem_mercadoria,
                'cfop_entrada'      => $i->cfopEntrada?->codigo,
                'cst_entrada'       => $i->cst_csosn_entrada,
                'gera_credito'      => $i->gera_credito,
                'fiscal_manual'     => (bool) $i->fiscal_manual,
            ])
            ->values()
            ->all();

        return [
            'entrada'         => $entrada,
            'fornecedores'    => Fornecedor::ativos()->orderBy('nome')->get(),
            'itens'           => $itens,
            'somenteLeitura'  => $entrada->exists && ! $entrada->isRascunho(),
            'operacoes'       => $this->operacoesParaModal(),      
            'formasPagamento' => FormaPagamento::ativos()->paraEntrada()
                ->orderByRaw('ordem IS NULL, ordem ASC, descricao ASC')->get(), 
            'fiscal'          => $this->regrasFiscais(),     
        ];
    }


    /** Regras de conversão enviadas à tela para o cálculo ao vivo na grade. */
    private function regrasFiscais(): array
    {
        $empresa = Empresa::atual();
        $regime = $empresa->crt == 3 ? 'normal' : 'simples';

        $cfops = [];
        foreach (CfopEntradaConversao::with('cfopEntrada:id,codigo')->get() as $c) {
            $cfops[$c->operacao_entrada_id][$c->cfop_origem] = $c->cfopEntrada?->codigo;
        }

        $csts = CstEntradaConversao::where('regime', $regime)->get()
            ->mapWithKeys(fn ($c) => [
                $c->tipo_origem . ':' . $c->codigo_origem => ['cst' => $c->codigo_entrada, 'credito' => (bool) $c->gera_credito],
            ])->all();

        return [
            'regime'       => $regime,
            'ufEmpresa'    => $empresa->getAttributes()['uf'] ?? null,
            'cfops'        => $cfops,
            'csts'         => $csts,
            'descricoes'   => CfopEntrada::pluck('descricao', 'codigo'),
            'cfopsEntrada' => CfopEntrada::where('ativo', true)->orderBy('codigo')->get(['codigo', 'descricao']),
            'opcoesCst'    => collect($regime === 'normal' ? self::CST : self::CSOSN)
                                ->map(fn ($descricao, $codigo) => ['codigo' => (string) $codigo, 'descricao' => $descricao])
                                ->values(),
        ];
    }

    /** Operações com os CFOPs principais (o que a 5102/6102 do fornecedor vira). */
    private function operacoesParaModal()
    {
        $codigos = CfopEntrada::pluck('codigo', 'id');
        $conversoes = CfopEntradaConversao::get()->groupBy('operacao_entrada_id');
        $mapa = CfopEntradaConversao::whereIn('cfop_origem', ['5102', '6102'])
            ->get()
            ->groupBy('operacao_entrada_id');

        return OperacaoEntrada::where('ativo', true)
            ->orderBy('ordem')
            ->orderBy('descricao')
            ->get()
            ->map(function ($op) use ($codigos, $conversoes, $mapa) {
                // sufixos (3 últimos dígitos) usados pelas regras desta operação
                $sufixos = collect($conversoes->get($op->id, []))
                    ->map(fn ($c) => substr((string) $codigos->get($c->cfop_entrada_id), 1))
                    ->filter()->unique()->values();

                $baseConversao = collect($mapa->get($op->id, []))->firstWhere('cfop_origem', '5102');
                $sufBase = $baseConversao ? substr((string) $codigos->get($baseConversao->cfop_entrada_id), 1) : null;

                // operação criada por esta tela usa no máximo 2 sufixos (normal e ST): dá para editar os CFOPs com segurança
                $editavel = $sufBase && $sufixos->count() <= 2;
                $sufSt = ($editavel && $sufixos->count() === 2) ? $sufixos->first(fn ($s) => $s !== $sufBase) : null;

                return [
                    'id'                => $op->id,
                    'descricao'         => $op->descricao,
                    'cfops'             => collect($mapa->get($op->id, []))
                        ->sortBy('cfop_origem')
                        ->map(fn ($c) => $codigos->get($c->cfop_entrada_id))
                        ->filter()
                        ->unique()
                        ->implode(' / '),
                    'cfop'              => $sufBase ? '1' . $sufBase : null,
                    'cfop_st'           => $sufSt ? '1' . $sufSt : null,
                    'cfop_editavel'     => (bool) $editavel,
                    'movimenta_estoque' => (bool) $op->movimenta_estoque,
                ];
            })
            ->values();
    }

    /** Garante no catálogo os CFOPs 1xxx/2xxx (e os de ST, se informados) de uma operação. */
    private function garantirCfopsNoCatalogo(string $descricao, string $sufixo, string $sufixoSt): void
    {
        foreach (['1', '2'] as $area) {
            CfopEntrada::firstOrCreate(
                ['codigo' => $area . $sufixo],
                ['descricao' => $descricao, 'ativo' => true]
            );

            if ($sufixoSt !== $sufixo) {
                CfopEntrada::firstOrCreate(
                    ['codigo' => $area . $sufixoSt],
                    ['descricao' => $descricao . ' (com ST)', 'ativo' => true]
                );
            }
        }
    }

    public function criarOperacao(Request $request)
    {
        $dados = $request->validate([
            'cfop'              => ['required', 'regex:/^[12]\d{3}$/'],
            'cfop_st'           => ['nullable', 'regex:/^[12]\d{3}$/'],
            'descricao'         => ['required', 'string', 'max:60', Rule::unique('operacoes_entrada', 'descricao')],
            'movimenta_estoque' => ['required', 'boolean'],
        ], [
            'cfop.regex'          => 'Informe o CFOP com 4 dígitos, começando por 1 (dentro do estado) ou 2 (outros estados).',
            'cfop_st.regex'       => 'O CFOP de ST deve ter 4 dígitos, começando por 1 ou 2.',
            'descricao.required'  => 'Informe a natureza da operação.',
            'descricao.unique'    => 'Já existe uma operação com esta natureza.',
        ]);

        $sufixo   = substr($dados['cfop'], 1);
        $sufixoSt = ! empty($dados['cfop_st']) ? substr($dados['cfop_st'], 1) : $sufixo;

        $operacao = DB::transaction(function () use ($dados, $sufixo, $sufixoSt) {
            $this->garantirCfopsNoCatalogo($dados['descricao'], $sufixo, $sufixoSt);

            $base = Str::limit(Str::slug($dados['descricao'], '_'), 36, '') ?: 'operacao';
            $codigo = $base;
            for ($n = 2; OperacaoEntrada::where('codigo', $codigo)->exists(); $n++) {
                $codigo = $base . '_' . $n;
            }

            $operacao = OperacaoEntrada::create([
                'codigo'            => $codigo,
                'descricao'         => $dados['descricao'],
                'movimenta_estoque' => $dados['movimenta_estoque'],
                'ordem'             => (int) OperacaoEntrada::max('ordem') + 1,
                'ativo'             => true,
            ]);

            CfopEntradaConversao::gerar($operacao, $sufixo, $sufixoSt, array_keys(CfopEntradaConversao::ORIGENS));

            return $operacao;
        });

        return response()->json($this->operacoesParaModal()->firstWhere('id', $operacao->id));
    }

    /**
     * Edita uma operação: natureza e "movimenta estoque" sempre; os CFOPs só quando
     * recriar_cfops = true (recria as regras de conversão da operação).
     */
    public function atualizarOperacao(Request $request, OperacaoEntrada $operacao)
    {
        $recriar = $request->boolean('recriar_cfops');

        $dados = $request->validate([
            'cfop'              => [Rule::requiredIf($recriar), 'nullable', 'regex:/^[12]\d{3}$/'],
            'cfop_st'           => ['nullable', 'regex:/^[12]\d{3}$/'],
            'descricao'         => ['required', 'string', 'max:60', Rule::unique('operacoes_entrada', 'descricao')->ignore($operacao->id)],
            'movimenta_estoque' => ['required', 'boolean'],
        ], [
            'cfop.required'       => 'Informe o CFOP de entrada.',
            'cfop.regex'          => 'Informe o CFOP com 4 dígitos, começando por 1 (dentro do estado) ou 2 (outros estados).',
            'cfop_st.regex'       => 'O CFOP de ST deve ter 4 dígitos, começando por 1 ou 2.',
            'descricao.required'  => 'Informe a natureza da operação.',
            'descricao.unique'    => 'Já existe uma operação com esta natureza.',
        ]);

        // o estorno de entradas finalizadas consulta "movimenta estoque": não pode mudar por baixo delas
        if ((bool) $operacao->movimenta_estoque !== (bool) $dados['movimenta_estoque']
            && EntradaNota::where('operacao_entrada_id', $operacao->id)->where('status', 'finalizada')->exists()) {
            throw ValidationException::withMessages([
                'movimenta_estoque' => 'Não é possível alterar "Movimenta estoque": já existem entradas finalizadas com esta operação.',
            ]);
        }

        DB::transaction(function () use ($dados, $operacao, $recriar) {
            $operacao->update([
                'descricao'         => $dados['descricao'],
                'movimenta_estoque' => $dados['movimenta_estoque'],
            ]);

            if ($recriar) {
                $sufixo   = substr($dados['cfop'], 1);
                $sufixoSt = ! empty($dados['cfop_st']) ? substr($dados['cfop_st'], 1) : $sufixo;

                $this->garantirCfopsNoCatalogo($dados['descricao'], $sufixo, $sufixoSt);

                CfopEntradaConversao::where('operacao_entrada_id', $operacao->id)->delete();
                CfopEntradaConversao::gerar($operacao, $sufixo, $sufixoSt, array_keys(CfopEntradaConversao::ORIGENS));
            }
        });

        return response()->json($this->operacoesParaModal()->firstWhere('id', $operacao->id));
    }

    private function validar(Request $request, ?EntradaNota $entrada): array
    {
        $cstValidos = array_keys(Empresa::atual()->crt == 3 ? self::CST : self::CSOSN);
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
            'forma_pagamento_id' => ['nullable', Rule::exists('formas_pagamento', 'id')
                ->where(fn ($q) => $q->where('ativo', true)->where('uso_entrada', true))],
            'data_emissao'      => ['required', 'date'],
            'data_entrada'      => ['required', 'date', 'after_or_equal:data_emissao'],
            'valor_frete'       => ['nullable', 'numeric', 'min:0'],
            'valor_desconto'    => ['nullable', 'numeric', 'min:0'],
            'valor_outras'      => ['nullable', 'numeric', 'min:0'],
            'observacao'        => ['nullable', 'string', 'max:1000'],

            'itens'                     => ['required', 'array', 'min:1'],
            'itens.*.produto_id'        => ['required', 'exists:produtos,id'],
            'itens.*.produto_variante_id' => ['nullable', 'integer', 'exists:produto_variantes,id'],
            'itens.*.descricao'         => ['nullable', 'string', 'max:200'],
            'itens.*.codigo_fornecedor' => ['nullable', 'string', 'max:60'],
            'itens.*.quantidade'        => ['required', 'numeric', 'gt:0'],
            'itens.*.qtd_por_caixa'     => ['nullable', 'integer', 'min:1', 'max:99999'],
            'itens.*.valor_unitario'    => ['required', 'numeric', 'min:0'],
            'itens.*.valor_desconto'    => ['nullable', 'numeric', 'min:0'],
            'itens.*.valor_outras_despesas' => ['nullable', 'numeric', 'min:0'],
            'itens.*.valor_frete'       => ['nullable', 'numeric', 'min:0'],
            'itens.*.bc_icms'           => ['nullable', 'numeric', 'min:0'],
            'itens.*.valor_icms'        => ['nullable', 'numeric', 'min:0'],
            'itens.*.aliquota_icms'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'itens.*.valor_ipi'         => ['nullable', 'numeric', 'min:0'],
            'itens.*.aliquota_ipi'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'itens.*.lote'              => ['nullable', 'string', 'max:30'],
            'itens.*.validade'          => ['nullable', 'date'],
            'itens.*.cfop_origem'       => ['nullable', 'digits:4'],
            'itens.*.cst_origem'        => ['nullable', 'string', 'max:3'],
            'itens.*.csosn_origem'      => ['nullable', 'string', 'max:3'],
            'itens.*.origem_mercadoria' => ['nullable', 'integer', 'between:0,8'],
            'itens.*.fiscal_manual'     => ['nullable', 'boolean'],
            'itens.*.cfop_entrada'      => ['nullable', 'digits:4', Rule::exists('cfops_entrada', 'codigo')],
            'itens.*.cst_entrada'       => ['nullable', Rule::in($cstValidos)],
            'itens.*.gera_credito'      => ['nullable', 'boolean'],
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

    private function formaDoXml(?array $pag): ?FormaPagamento
    {
        if (! $pag || empty($pag['meio'])) {
            return null;
        }

        $ind = ($pag['condicao'] ?? null) === 'prazo' ? 1 : 0;

        $base = FormaPagamento::ativos()->paraEntrada()
            ->where('meio_pagamento', $pag['meio'])
            ->orderByRaw('ordem IS NULL, ordem ASC');

        return (clone $base)->where('ind_pag', $ind)->first() ?? (clone $base)->first();
    }

    private function salvar(Request $request, ?EntradaNota $entrada): EntradaNota
    {
        // Nota importada do XML: os dados do documento não mudam; vale o que está gravado
        if ($entrada && $entrada->tipo_entrada === 'xml') {
            $request->merge([
                'fornecedor_id' => $entrada->fornecedor_id,
                'modelo'        => $entrada->modelo,
                'serie'         => $entrada->serie,
                'numero'        => $entrada->numero,
                'chave_acesso'  => $entrada->chave_acesso,
                'data_emissao'  => $entrada->data_emissao?->format('Y-m-d'),
                'data_entrada'  => $entrada->data_entrada?->format('Y-m-d'),
            ]);
        }
        $dados = $this->validar($request, $entrada);

        $produtos = Produto::whereIn('id', collect($dados['itens'])->pluck('produto_id')->unique())
            ->get()
            ->keyBy('id');
        $cfopIds = CfopEntrada::pluck('id', 'codigo');

        $variantes = ProdutoVariante::whereIn(
            'id',
            collect($dados['itens'])->pluck('produto_variante_id')->filter()->unique()
        )->get()->keyBy('id');

        foreach ($dados['itens'] as $i => $item) {
            $produto  = $produtos[$item['produto_id']];
            $variante = ! empty($item['produto_variante_id']) ? $variantes[$item['produto_variante_id']] : null;

            if ($produto->tem_variacao && ! $variante) {
                throw ValidationException::withMessages([
                    "itens.{$i}.produto_id" => 'Produtos com variação só podem ser lançados pela importação de XML (distribuindo a quantidade entre as variações).',
                ]);
            }

            if ($variante && (! $produto->tem_variacao || (int) $variante->produto_id !== (int) $produto->id)) {
                throw ValidationException::withMessages([
                    "itens.{$i}.produto_id" => 'A variação informada não pertence ao produto.',
                ]);
            }

            // o estoque da variação é inteiro
            if ($variante && fmod((float) $item['quantidade'] * max((int) ($item['qtd_por_caixa'] ?? 1), 1), 1.0) != 0.0) {
                throw ValidationException::withMessages([
                    "itens.{$i}.quantidade" => 'Para produtos com variação a quantidade em estoque deve ser inteira.',
                ]);
            }
        }

            return DB::transaction(function () use ($request, $dados, $entrada, $produtos, $variantes, $cfopIds) {
            $entrada ??= new EntradaNota([
                'status'       => 'rascunho',
                'tipo_entrada' => 'manual',
                'user_id'      => auth()->id(),
            ]);

            $entrada->fill(Arr::except($dados, ['itens']));
            $entrada->natureza_operacao = Str::limit(
                (string) OperacaoEntrada::whereKey($dados['operacao_entrada_id'])->value('descricao'),
                60,
                ''
            );
            $entrada->valor_frete     = $dados['valor_frete'] ?? 0;
            $entrada->valor_desconto  = $dados['valor_desconto'] ?? 0;
            $entrada->valor_outras    = $dados['valor_outras'] ?? 0;
            $entrada->atualizar_custo = $request->boolean('atualizar_custo');
            $entrada->save();

            // Rascunho: recria os itens a cada gravação
            $entrada->itens()->delete();

            foreach ($dados['itens'] as $item) {
                $produto  = $produtos[$item['produto_id']];
                $qtd      = (float) $item['quantidade'];
                $unit     = (float) $item['valor_unitario'];
                $desconto = (float) ($item['valor_desconto'] ?? 0);
                $manual = filter_var($item['fiscal_manual'] ?? false, FILTER_VALIDATE_BOOLEAN);

                $entrada->itens()->create([
                    'produto_id'        => $produto->id,
                    'produto_variante_id' => ! empty($item['produto_variante_id']) ? $variantes[$item['produto_variante_id']]->id : null,
                    'codigo_fornecedor' => $item['codigo_fornecedor'] ?? null,
                    'descricao'         => trim((string) ($item['descricao'] ?? '')) ?: $produto->nome,
                    'unidade'           => $produto->unidade_comercial,
                    'quantidade'        => $qtd,
                    'qtd_por_caixa'     => (int) (($item['qtd_por_caixa'] ?? null) ?: 1),
                    'valor_unitario'    => $unit,
                    'valor_desconto'    => $desconto,
                    'valor_total'       => round(($qtd * $unit) - $desconto, 2),
                    'valor_outras_despesas' => round((float) ($item['valor_outras_despesas'] ?? 0), 2),
                    'valor_frete'           => round((float) ($item['valor_frete'] ?? 0), 2),
                    'bc_icms'               => round((float) ($item['bc_icms'] ?? 0), 2),
                    'valor_icms'            => round((float) ($item['valor_icms'] ?? 0), 2),
                    'aliquota_icms'         => round((float) ($item['aliquota_icms'] ?? 0), 4),
                    'valor_ipi'             => round((float) ($item['valor_ipi'] ?? 0), 2),
                    'aliquota_ipi'          => round((float) ($item['aliquota_ipi'] ?? 0), 4),
                    'lote'              => $item['lote'] ?? null,
                    'validade'          => $item['validade'] ?? null,
                    'cfop_origem'       => $item['cfop_origem'] ?? null,
                    'cst_origem'        => $item['cst_origem'] ?? null,
                    'csosn_origem'      => $item['csosn_origem'] ?? null,
                    'origem_mercadoria' => $item['origem_mercadoria'] ?? null,
                    'fiscal_manual'     => $manual,
                    ...($manual ? [
                    'cfop_entrada_id'   => ! empty($item['cfop_entrada']) ? $cfopIds[$item['cfop_entrada']] : null,
                    'cst_csosn_entrada' => ($item['cst_entrada'] ?? null) ?: null,
                    'gera_credito'      => isset($item['gera_credito']) && $item['gera_credito'] !== ''
                                                ? filter_var($item['gera_credito'], FILTER_VALIDATE_BOOLEAN) : null,
                    ] : []),
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

        foreach ($entrada->itens->reject(fn ($i) => $i->fiscal_manual) as $item) {
            $item->update($conversao->converter(
                $item->cfop_origem ?: $presumido,
                $item->cst_origem,
                $item->csosn_origem
            ));
        }
    }

    /**
     * Desfaz o finalizar(): subtrai o estoque, remove os lotes gerados e volta a nota para rascunho.
     * Lança RuntimeException (e nada é gravado) se o estorno não for possível.
     */
    private function estornar(EntradaNota $entrada): void
    {
        DB::transaction(function () use ($entrada) {
            $entrada = EntradaNota::whereKey($entrada->id)->lockForUpdate()->firstOrFail();

            if (! $entrada->isFinalizada()) {
                return; // proteção contra duplo clique / reenvio
            }

            // conta a pagar gerada na finalização: sai junto; se já houver parcela paga, o estorno é bloqueado
            app(ContaPagarService::class)->removerDaEntrada($entrada);

            $entrada->load(['operacao', 'itens']);
            $movimenta = $entrada->operacao?->movimenta_estoque ?? true;

            if ($movimenta) {
                $itemIds = $entrada->itens->pluck('id');

                // lote que já teve baixa não pode ser desfeito sem descontrolar o estoque por lote
                $consumidos = ProdutoLote::whereIn('entrada_nota_item_id', $itemIds)
                    ->whereColumn('quantidade_atual', '<', 'quantidade_inicial')
                    ->get();

                if ($consumidos->isNotEmpty()) {
                    throw new \RuntimeException(
                        'Não é possível estornar: já houve baixa nos lotes ' . $consumidos->pluck('lote')->filter()->implode(', ') . '.'
                    );
                }

                // total que entrou por produto (sem variação) e por variante; o mesmo destino pode aparecer em mais de um item
                $porProduto = $entrada->itens
                    ->whereNull('produto_variante_id')
                    ->groupBy('produto_id')
                    ->map(fn ($itens) => $itens->sum(fn ($i) => $i->quantidade_estoque));

                $porVariante = $entrada->itens
                    ->whereNotNull('produto_variante_id')
                    ->groupBy('produto_variante_id')
                    ->map(fn ($itens) => (int) round($itens->sum(fn ($i) => $i->quantidade_estoque)));

                $produtos  = Produto::whereIn('id', $porProduto->keys())->lockForUpdate()->get()->keyBy('id');
                $variantes = ProdutoVariante::whereIn('id', $porVariante->keys())->lockForUpdate()->get()->keyBy('id');

                foreach ($porProduto as $produtoId => $qtd) {
                    $produto = $produtos[$produtoId];

                    if ((float) $produto->estoque < $qtd) {
                        throw new \RuntimeException(
                            "Estoque insuficiente para estornar \"{$produto->nome}\": estoque atual {$produto->estoque}, a estornar {$qtd}."
                        );
                    }
                }

                foreach ($porVariante as $varId => $qtd) {
                    $variante = $variantes[$varId];

                    if ((int) $variante->estoque < $qtd) {
                        $nome = $entrada->itens->firstWhere('produto_variante_id', $varId)?->descricao;
                        throw new \RuntimeException(
                            "Estoque insuficiente para estornar \"{$nome}\": estoque atual {$variante->estoque}, a estornar {$qtd}."
                        );
                    }
                }

                // [MOVIMENTAÇÃO] estorno pelo EstoqueService (as validações de saldo acima continuam valendo)
                $estoque = app(EstoqueService::class);
                $contexto = [
                    'documento_tipo'   => 'entrada_nota',
                    'documento_id'     => $entrada->id,
                    'documento_rotulo' => "Entrada nº {$entrada->numero} série {$entrada->serie}",
                    'observacao'       => 'Estorno da entrada de nota',
                ];

                foreach ($porProduto as $produtoId => $qtd) {
                    $estoque->movimentar((int) $produtoId, null, -(float) $qtd, EstoqueService::ENTRADA_ESTORNADA, $contexto);
                }

                foreach ($porVariante as $varId => $qtd) {
                    $estoque->movimentar((int) $variantes[$varId]->produto_id, (int) $varId, -(int) $qtd, EstoqueService::ENTRADA_ESTORNADA, $contexto);
                }

                ProdutoLote::whereIn('entrada_nota_item_id', $itemIds)->delete();
            }

            $entrada->update([
                'status'        => 'rascunho',
                'finalizada_em' => null,
            ]);
        });
    }

    /** Parcelas do Contas a Pagar enviadas pelo modal de finalização (null = não lançar). */
    private function parcelasContasPagar(Request $request): ?array
    {
        if ($request->input('acao') !== 'finalizar' || ! $request->boolean('lancar_contas_pagar')) {
            return null;
        }

        return app(ContaPagarService::class)->normalizarParcelas((array) $request->input('cp_parcelas', []));
    }

    private function mensagemFinalizada(?array $parcelasCp): string
    {
        return $parcelasCp === null
            ? 'Entrada finalizada e estoque atualizado.'
            : 'Entrada finalizada, estoque atualizado e ' . count($parcelasCp) . ' parcela(s) lançada(s) no Contas a Pagar.';
    }

    /**
     * Aplica a entrada: soma estoque, atualiza custo (opcional) e gera lotes.
     */
    private function finalizar(EntradaNota $entrada, ?array $parcelasCp = null): void
    {
        DB::transaction(function () use ($entrada, $parcelasCp) {
            $entrada = EntradaNota::whereKey($entrada->id)->lockForUpdate()->firstOrFail();

            if (! $entrada->isRascunho()) {
                return; // proteção contra duplo clique / reenvio
            }
            $movimenta = $entrada->operacao?->movimenta_estoque ?? true;

            foreach ($movimenta ? $entrada->itens : [] as $item) {
                $produto = Produto::whereKey($item->produto_id)->lockForUpdate()->firstOrFail();

                // quantidade da nota (caixas) × unidades por caixa = unidades que entram no estoque
                $qtdEstoque = $item->quantidade_estoque;

                // [MOVIMENTAÇÃO] passa pelo EstoqueService: soma no saldo e registra no histórico.
                // Produto com variação: o estoque mora na variante (inteiro).
                app(EstoqueService::class)->movimentar(
                    (int) $item->produto_id,
                    $item->produto_variante_id ? (int) $item->produto_variante_id : null,
                    $item->produto_variante_id ? (int) round($qtdEstoque) : (float) $qtdEstoque,
                    EstoqueService::ENTRADA_COMPRA,
                    [
                        'documento_tipo'   => 'entrada_nota',
                        'documento_id'     => $entrada->id,
                        'documento_rotulo' => "Entrada nº {$entrada->numero} série {$entrada->serie}",
                        // custo líquido por unidade desta compra (o custo do cadastro pode nem ser atualizado)
                        'custo_unitario'   => $qtdEstoque > 0 ? round((float) $item->valor_total / $qtdEstoque, 4) : null,
                    ]
                );

                if ($entrada->atualizar_custo && $qtdEstoque > 0) {
                    // custo líquido por UNIDADE (total do item, já com desconto, ÷ unidades)
                    $produto->update([
                        'preco_custo' => round((float) $item->valor_total / $qtdEstoque, 2),
                    ]);
                }

                if ($item->lote || $item->validade) {
                    ProdutoLote::create([
                        'produto_id'           => $item->produto_id,
                        'produto_variante_id'  => $item->produto_variante_id,
                        'entrada_nota_item_id' => $item->id,
                        'lote'                 => $item->lote,
                        'validade'             => $item->validade,
                        'quantidade_inicial'   => $qtdEstoque,
                        'quantidade_atual'     => $qtdEstoque,
                    ]);
                }
            }

            $entrada->update([
                'status'        => 'finalizada',
                'finalizada_em' => now(),
            ]);

            // lançamento no Contas a Pagar (escolhido no modal); na mesma transação da finalização
            if ($parcelasCp !== null) {
                app(ContaPagarService::class)->criarDaEntrada($entrada, $parcelasCp);
            }
        });
    }
}