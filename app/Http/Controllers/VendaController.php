<?php

namespace App\Http\Controllers;

use App\Models\CaixaCache;
use App\Support\CentralStatus;
use App\Services\SyncService;
use Illuminate\Http\Request;
use App\Support\AutorizacaoSupervisor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class VendaController extends Controller
{
    public function pdv()
    {
        $caixa = CaixaCache::aberto(Auth::id());

        // Caixa recém-aberto no servidor ainda pode não estar no espelho local
        if (!$caixa) {
            (new SyncService())->puxarPdvs();
            (new SyncService())->puxarCaixasAbertos();
            $caixa = CaixaCache::aberto(Auth::id());
        }

        if (!$caixa) {
            return redirect()->route('caixa.abrir-form');
        }

        // Abrir o PDV sem carrinho salvo na sessão = venda nova: descarta autorizações antigas
        if (!session()->has('venda_carrinho')) {
            AutorizacaoSupervisor::consumir('desconto_item');
            AutorizacaoSupervisor::consumir('desconto_global');
        }
        AutorizacaoSupervisor::consumir('cancelar_nfce');

        $carrinhoSalvo = session('venda_carrinho');
        $itensIniciais = $carrinhoSalvo['itens'] ?? [];
        $descontoGlobalInicial = $carrinhoSalvo['desconto_global'] ?? 0;
        $liberacoes = $this->liberacoesDoOperador();

        return view('vendas.pdv', compact('caixa', 'itensIniciais', 'descontoGlobalInicial', 'liberacoes'));
    }

/**
     * Busca produtos no banco LOCAL (sqlite), nao mais no MySQL central.
     */
    public function buscarProduto(Request $request)
    {
        $termo = $request->get('termo', '');

        // Se o termo e so digitos, tenta resolver por MATCH EXATO primeiro -
        // codigo interno, codigo de barras real, ou o fallback zero-padded de
        // 13 digitos (ex: termo "2" -> "0000000000002"). Isso e o que permite
        // digitar o codigo curto + Enter e ir direto pro carrinho, sem passar
        // pela lista de busca - igual um leitor de codigo de barras faria.
        if (ctype_digit($termo) && $termo !== '') {
            $codigoBarrasFallback = str_pad($termo, 13, '0', STR_PAD_LEFT);

            $exato = DB::connection('sqlite_local')->table('produtos_cache')
                ->where('ativo', true)
                ->where(function ($q) use ($termo, $codigoBarrasFallback) {
                    $q->where('codigo_interno', $termo)
                      ->orWhere('codigo_barras', $termo)
                      ->orWhere('codigo_barras', $codigoBarrasFallback);
                })
                ->first();

            if ($exato) {
                $exato->variantes = $exato->tem_variacao
                    ? DB::connection('sqlite_local')->table('produto_variantes_cache')
                        ->where('produto_id', $exato->id)->get()
                    : [];

                return response()->json([$exato]);
            }
        }

        // Sem match exato (ou termo nao e so digitos) - busca ampla, como ja era
        $produtos = DB::connection('sqlite_local')->table('produtos_cache')
            ->where('ativo', true)
            ->where(function ($q) use ($termo) {
                $q->where('nome', 'like', "%{$termo}%")
                  ->orWhere('codigo_interno', 'like', "%{$termo}%")
                  ->orWhere('codigo_barras', 'like', "%{$termo}%");
            })
            ->limit(10)
            ->get();

        $produtos = $produtos->map(function ($produto) {
            $produto->variantes = $produto->tem_variacao
                ? DB::connection('sqlite_local')->table('produto_variantes_cache')
                    ->where('produto_id', $produto->id)
                    ->get()
                : [];
            return $produto;
        });

        return response()->json($produtos);
    }


    private function liberacoesDoOperador(): array
    {
        $usuario = Auth::user();

        return collect(array_keys(config('permissoes.caixa.acoes')))
            ->mapWithKeys(fn ($acao) => [$acao => $usuario->caixaLiberado($acao)])
            ->all();
    }

    /**
     * Grava a venda no banco LOCAL (fila de pendentes), nao mais direto no MySQL.
     */

    public function prepararPagamento(Request $request)
    {
        $validado = $request->validate([
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|integer',
            'itens.*.produto_variante_id' => 'nullable|integer',
            'itens.*.nome' => 'required|string',
            'itens.*.quantidade' => 'required|integer|min:1',
            'itens.*.preco' => 'required|numeric',
            'itens.*.desconto' => 'nullable|numeric',
            'itens.*.subtotal' => 'required|numeric',
            'desconto_item' => 'required|numeric',
            'desconto_global' => 'required|numeric',
            'total' => 'required|numeric',
            'itens.*.desconto_bruto' => 'nullable|numeric',
        ]);

        session(['venda_carrinho' => $validado]);

        return response()->json(['sucesso' => true]);
    }

    public function telaPagamento()
    {
        $dados = session('venda_carrinho');

        if (!$dados) {
            return redirect()->route('vendas.pdv')->with('erro', 'Nenhum item no carrinho. Adicione itens antes de prosseguir.');
        }

        return view('vendas.pagamento', $dados + ['liberacoes' => $this->liberacoesDoOperador()]);
    }

    public function limparSessaoCarrinho()
    {
        session()->forget('venda_carrinho');
        AutorizacaoSupervisor::consumir('desconto_item');
        AutorizacaoSupervisor::consumir('desconto_global');
        return response()->json(['sucesso' => true]);
    }
    
    private function resolverPrecoUnitario($produto, $quantidade): float
    {
        $temAtacadoConfigurado = $produto->preco_atacado && $produto->quantidade_minima_atacado;
 
        if (!$temAtacadoConfigurado || $quantidade < $produto->quantidade_minima_atacado) {
            return (float) $produto->preco_venda;
        }
 
        if (!$produto->atacado_tem_prazo) {
            return (float) $produto->preco_atacado;
        }
 
        $hoje = now()->toDateString();
        $dentroDoPrazo = $produto->atacado_data_inicio && $produto->atacado_data_fim
            && $hoje >= $produto->atacado_data_inicio
            && $hoje <= $produto->atacado_data_fim;
 
        return $dentroDoPrazo ? (float) $produto->preco_atacado : (float) $produto->preco_venda;
    }
 
    public function finalizar(Request $request)
    {
        $validado = $request->validate([
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|integer',
            'itens.*.produto_variante_id' => 'nullable|integer',
            'itens.*.quantidade' => 'required|integer|min:1',
            'itens.*.desconto' => 'nullable|numeric|min:0',
            'pagamentos' => 'required|array|min:1',
            'pagamentos.*.forma_pagamento' => 'required|in:dinheiro,pix,credito,debito',
            'pagamentos.*.valor' => 'required|numeric|min:0.01',
            'desconto_global' => 'nullable|numeric|min:0',
            'cliente_id' => 'nullable|integer',
            'cpf_na_nota' => 'nullable|digits:11',
        ]);

        $caixa = CaixaCache::aberto(Auth::id());

        if (!$caixa) {
            return response()->json(['erro' => 'Nenhum caixa aberto.'], 422);
        }

        // Cada tipo de desconto exige: operador liberado OU autorização de supervisor válida
        $usuario = Auth::user();
        $descontoItens = collect($validado['itens'])->sum(fn ($i) => (float) ($i['desconto'] ?? 0));
        $descontoGlobalInformado = (float) ($validado['desconto_global'] ?? 0);

        $semAutorizacaoItem = $descontoItens > 0
            && !$usuario->caixaLiberado('desconto_item')
            && !AutorizacaoSupervisor::valida('desconto_item');

        $semAutorizacaoGlobal = $descontoGlobalInformado > 0
            && !$usuario->caixaLiberado('desconto_global')
            && !AutorizacaoSupervisor::valida('desconto_global');

        if ($semAutorizacaoItem || $semAutorizacaoGlobal) {
            return response()->json([
                'erro' => 'Desconto sem autorização do supervisor. Solicite a autorização novamente.',
            ], 403);
        }

        try {
            $uuid = (string) Str::uuid();
            $totalComDesconto = 0;

            // Baixa de estoque, conferências e gravação da venda pendente na MESMA transação:
            // se qualquer conferência falhar, a baixa de estoque é desfeita junto.
            DB::connection('sqlite_local')->transaction(function () use ($validado, $caixa, $uuid, &$totalComDesconto) {
                $total = 0;
                $itensParaSalvar = [];

                foreach ($validado['itens'] as $item) {
                    if (!empty($item['produto_variante_id'])) {
                        $variante = DB::connection('sqlite_local')->table('produto_variantes_cache')
                            ->where('id', $item['produto_variante_id'])->lockForUpdate()->first();

                        if (!$variante || $variante->estoque < $item['quantidade']) {
                            throw new \Exception('Estoque insuficiente (local) para o item selecionado.');
                        }

                        DB::connection('sqlite_local')->table('produto_variantes_cache')
                            ->where('id', $variante->id)
                            ->decrement('estoque', $item['quantidade']);

                        $produto = DB::connection('sqlite_local')->table('produtos_cache')
                            ->where('id', $item['produto_id'])->first();
                    } else {
                        $produto = DB::connection('sqlite_local')->table('produtos_cache')
                            ->where('id', $item['produto_id'])->lockForUpdate()->first();

                        if (!$produto || $produto->estoque < $item['quantidade']) {
                            throw new \Exception('Estoque insuficiente (local) para ' . ($produto->nome ?? 'produto'));
                        }

                        DB::connection('sqlite_local')->table('produtos_cache')
                            ->where('id', $produto->id)
                            ->decrement('estoque', $item['quantidade']);
                    }

                    $precoUnitario = $this->resolverPrecoUnitario($produto, $item['quantidade']);

                    $desconto = min($item['desconto'] ?? 0, $precoUnitario * $item['quantidade']);
                    $subtotal = ($precoUnitario * $item['quantidade']) - $desconto;
                    $total += $subtotal;

                    $itensParaSalvar[] = [
                        'produto_id' => $item['produto_id'],
                        'produto_variante_id' => $item['produto_variante_id'] ?? null,
                        'quantidade' => $item['quantidade'],
                        'preco_unitario' => $precoUnitario,
                        'desconto' => $desconto,
                    ];
                }

                $descontoGlobal = $validado['desconto_global'] ?? 0;
                $descontoTotal = collect($itensParaSalvar)->sum('desconto') + $descontoGlobal;

                // Abate o desconto global do total antes de conferir os pagamentos
                $totalComDesconto = $total - $descontoGlobal;

                if ($totalComDesconto < 0) {
                    throw new \Exception('Desconto global maior que o total da venda.');
                }

                $totalPagamentos = collect($validado['pagamentos'])->sum('valor');
                $troco = round($totalPagamentos - $totalComDesconto, 2);

                if ($troco < -0.01) {
                    throw new \Exception('A soma dos pagamentos é menor que o total da venda.');
                }

                DB::connection('sqlite_local')->table('vendas_pendentes')->insert([
                    'uuid' => $uuid,
                    'caixa_id_central' => $caixa->id,
                    'operador_id_central' => Auth::id(),
                    'cliente_id' => $validado['cliente_id'] ?? null,
                    'cpf_na_nota' => $validado['cpf_na_nota'] ?? null,
                    'total' => $totalComDesconto,
                    'troco' => $troco,
                    'desconto' => $descontoTotal,
                    'forma_pagamento' => null,
                    'pagamentos' => json_encode($validado['pagamentos']),
                    'itens' => json_encode($itensParaSalvar),
                    'status' => 'pendente_sync',
                    'vendida_em' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            // Venda gravada: encerra o carrinho e a autorização de desconto desta venda
            session()->forget('venda_carrinho');
            AutorizacaoSupervisor::consumir('desconto_item');
            AutorizacaoSupervisor::consumir('desconto_global');

            $emissao = ['sucesso' => false, 'contingencia' => false, 'erro' => null];

            if (CentralStatus::fora()) {
                // Servidor fora do ar: a venda fica no caixa e sobe pelo agendador quando a conexão voltar
                $emissao['erro'] = 'Servidor indisponível: a venda foi salva no caixa e será enviada quando a conexão voltar.';
            } else {
                try {
                    (new SyncService())->enviarVendasPendentes();

                    $vendaCentral = \App\Models\Venda::where('uuid', $uuid)->first();

                    if ($vendaCentral) {
                        try {
                            $resultado = (new \App\Services\FiscalEmissorService())->emitir($vendaCentral);
                            $emissao = ['sucesso' => true, 'contingencia' => false, 'chave' => $resultado['chave']];
                        } catch (\Exception $e) {
                            $vendaCentral->refresh();
                            $emissao = [
                                'sucesso' => false,
                                'contingencia' => $vendaCentral->status === 'contingencia',
                                'erro' => $e->getMessage(),
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    if (CentralStatus::erroDeConexao($e)) {
                        CentralStatus::marcarFora();
                        $emissao['erro'] = 'Servidor indisponível: a venda foi salva no caixa e será enviada quando a conexão voltar.';
                    }
                    // Nem a sincronização rolou - venda fica local, o scheduler tenta depois
                }
            }

            return response()->json([
                'sucesso' => true,
                'venda_uuid' => $uuid,
                'total' => $totalComDesconto,
                'emissao' => $emissao,
            ]);
        } catch (\Exception $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        }
    }
}