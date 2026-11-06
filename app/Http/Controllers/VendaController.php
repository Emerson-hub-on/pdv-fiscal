<?php

namespace App\Http\Controllers;

use App\Models\CaixaLocal;
use App\Support\CentralStatus;
use App\Support\Maquina;
use App\Services\SyncService;
use Illuminate\Http\Request;
use App\Support\AutorizacaoSupervisor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\EmissorLocalService;
use App\Support\EmissaoLocal;
use App\Models\PdvCache;
use App\Support\OcorrenciaCaixa;


class VendaController extends Controller
{
    public function pdv()
    {
        $caixa = CaixaLocal::aberto(Auth::id());

        // Caixa aberto no servidor por outro caminho: traz para cá (só se o servidor responder)
        if (!$caixa && !CentralStatus::fora()) {
            (new SyncService())->puxarPdvs();
            (new SyncService())->puxarCaixasAbertos();
            $caixa = CaixaLocal::aberto(Auth::id());
        }

        if (!$caixa) {
            return redirect()->route('caixa.abrir-form');
        }

        // Caixa sem o PDV no cadastro local (caixa novo ou PDV trocado de computador): tenta trazer uma vez
        if (!$caixa->pdv) {
            if (!CentralStatus::fora()) {
                (new SyncService())->puxarPdvs();
                $caixa->unsetRelation('pdv');
            }

            if (!$caixa->pdv) {
                abort(503, 'O PDV deste caixa (nº ' . $caixa->pdv_id . ') não está cadastrado para este computador ('
                    . Maquina::nome() . '). Confira o campo máquina do PDV no painel e clique em Atualizar Caixa.');
            }
        }

        // Abrir o PDV sem carrinho salvo na sessão = venda nova: descarta autorizações antigas
        if (!session()->has('venda_carrinho')) {
            AutorizacaoSupervisor::consumir('desconto_item');
            AutorizacaoSupervisor::consumir('desconto_global');
        }
        AutorizacaoSupervisor::consumir('cancelar_nfce');
        AutorizacaoSupervisor::consumir('inutilizar');

        $carrinhoSalvo = session('venda_carrinho');
        $itensIniciais = $carrinhoSalvo['itens'] ?? [];
        $descontoGlobalInicial = $carrinhoSalvo['desconto_global'] ?? 0;
        $liberacoes = $this->liberacoesDoOperador();

        $pdvInativo = $this->pdvInativo($caixa);

        $pedidoVendaIdInicial = $carrinhoSalvo['pedido_venda_id'] ?? null;
        $pedidoVendaNumeroInicial = $pedidoVendaIdInicial
            ? DB::connection('sqlite_local')->table('pedidos_venda_cache')
                ->where('id', $pedidoVendaIdInicial)->value('numero')
            : null;

        return view('vendas.pdv', compact(
            'caixa', 'itensIniciais', 'descontoGlobalInicial', 'liberacoes', 'pdvInativo',
            'pedidoVendaIdInicial', 'pedidoVendaNumeroInicial'
        ));
        
    }

/**
     * Busca produtos no banco LOCAL (sqlite), nao mais no MySQL central.
     */
    public function buscarProduto(Request $request)
    {
        $termo = $request->get('termo', '');

        // Codigo de barras de VARIACAO (EAN real ou codigo interno gerado, ex:
        // 9900000000028): resolve direto pra variacao e devolve so ela, junto
        // com o produto pai (preco e dados fiscais vem do pai).
        if (ctype_digit($termo) && $termo !== '') {
            $variante = DB::connection('sqlite_local')->table('produto_variantes_cache')
                ->where('codigo_barras', $termo)
                ->first();

            if ($variante) {
                $pai = DB::connection('sqlite_local')->table('produtos_cache')
                    ->where('id', $variante->produto_id)
                    ->where('ativo', true)
                    ->first();

                if ($pai) {
                    $pai->variantes = [$variante];

                    return response()->json([$pai]);
                }
            }
        }

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
                  ->orWhere('codigo_barras', 'like', "%{$termo}%")
                  // produtos cuja variacao tem esse codigo (busca parcial)
                  ->orWhereIn('id', DB::connection('sqlite_local')->table('produto_variantes_cache')
                      ->select('produto_id')
                      ->where('codigo_barras', 'like', "%{$termo}%"));
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
            'pedido_venda_id' => 'nullable|integer',
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

    // Uma ocorrência por item com desconto e uma pelo desconto no total, com o supervisor que liberou
    private function registrarDescontos(string $uuid, CaixaLocal $caixa, array $itens, float $descontoGlobal): void
    {
        $usuario = Auth::user();
        $db = DB::connection('sqlite_local');

        $liberadoItem = $usuario->caixaLiberado('desconto_item');
        $supervisorItem = $liberadoItem ? null : AutorizacaoSupervisor::supervisorId('desconto_item');

        foreach ($itens as $item) {
            if ((float) $item['desconto'] <= 0) {
                continue;
            }

            $nome = $db->table('produtos_cache')->where('id', $item['produto_id'])->value('nome')
                ?? 'Produto #' . $item['produto_id'];

            OcorrenciaCaixa::registrar([
                'tipo' => OcorrenciaCaixa::DESCONTO_ITEM,
                'venda_uuid' => $uuid,
                'caixa_uuid' => $caixa->uuid,
                'descricao' => $nome . ' x' . $item['quantidade'],
                'valor' => $item['desconto'],
                'supervisor_id' => $supervisorItem,
                'liberado_operador' => $liberadoItem,
            ]);
        }

        if ($descontoGlobal > 0) {
            $liberadoGlobal = $usuario->caixaLiberado('desconto_global');

            OcorrenciaCaixa::registrar([
                'tipo' => OcorrenciaCaixa::DESCONTO_GLOBAL,
                'venda_uuid' => $uuid,
                'caixa_uuid' => $caixa->uuid,
                'descricao' => 'Desconto no total da venda',
                'valor' => $descontoGlobal,
                'supervisor_id' => $liberadoGlobal ? null : AutorizacaoSupervisor::supervisorId('desconto_global'),
                'liberado_operador' => $liberadoGlobal,
            ]);
        }
    }

    // PDV do caixa foi inativado no servidor (já sincronizado para o cache local)
    private function pdvInativo(CaixaLocal $caixa): bool
    {
        $pdv = PdvCache::find($caixa->pdv_id);

        return $pdv && !$pdv->ativo;
    }

    // Emite a NFC-e da venda pelo emissor local. A contingência (tpEmis 9) também termina em exceção, mas o documento já é válido.
    private function emitirNoCaixa(string $uuid): array
    {
        try {
            $resultado = (new EmissorLocalService())->emitirLocal($uuid);

            return ['sucesso' => true, 'contingencia' => false, 'chave' => $resultado['chave']];
        } catch (\Throwable $e) {
            $situacao = DB::connection('sqlite_local')->table('vendas_pendentes')
                ->where('uuid', $uuid)->value('status_fiscal');

            return [
                'sucesso' => false,
                'contingencia' => $situacao === 'contingencia',
                'erro' => $e->getMessage(),
            ];
        }
    }
 
    /**
     * Pagamento com crédito em loja (troca/haver): confere no SQLite do caixa e abate o saldo
     * (dentro da transação da venda). O servidor registra o uso quando a venda sobe.
     * Crédito não dá troco: a soma dos créditos não pode passar do total da venda.
     */
    private function abaterCreditoLoja(array $pagamentos, float $totalVenda, $clienteId): void
    {
        $usos = collect($pagamentos)->where('forma_pagamento', 'credito_loja')
            ->groupBy(fn ($p) => (int) ($p['credito_loja_id'] ?? 0))
            ->map(fn ($g) => round((float) $g->sum('valor'), 2));

        if ($usos->isEmpty()) {
            return;
        }

        if ($usos->sum() - $totalVenda > 0.009) {
            throw new \Exception('O crédito em loja não pode ser maior que o total da venda (crédito não dá troco).');
        }

        $db = DB::connection('sqlite_local');

        foreach ($usos as $creditoId => $valor) {
            $credito = $db->table('creditos_loja_cache')->where('id', $creditoId)->lockForUpdate()->first();

            if (!$credito || $credito->status !== 'ativo') {
                throw new \Exception('Crédito em loja não encontrado neste caixa. Use "Atualizar Caixa" e tente de novo.');
            }

            // Crédito com dono só vale para ele; vale-troca sem cliente vale pelo código
            if ($credito->cliente_id && (int) $credito->cliente_id !== (int) $clienteId) {
                throw new \Exception("O crédito {$credito->codigo} é de outro cliente. Identifique o consumidor dono do crédito.");
            }

            if ($valor - (float) $credito->saldo > 0.009) {
                throw new \Exception(sprintf('O crédito %s tem só R$ %s de saldo.', $credito->codigo, number_format((float) $credito->saldo, 2, ',', '.')));
            }

            $db->table('creditos_loja_cache')->where('id', $creditoId)
                ->update(['saldo' => round((float) $credito->saldo - $valor, 2), 'updated_at' => now()]);
        }
    }

    /** Caixa: créditos em loja do cliente da venda, ou de um vale-troca pelo código (JSON). */
    public function creditosLoja(Request $request)
    {
        $db = DB::connection('sqlite_local');

        if (!$db->getSchemaBuilder()->hasTable('creditos_loja_cache')) {
            return response()->json([]);
        }

        $codigo = strtoupper(trim((string) $request->get('codigo', '')));
        $clienteId = (int) $request->get('cliente_id', 0);

        $creditos = $db->table('creditos_loja_cache')
            ->where('status', 'ativo')->where('saldo', '>', 0)
            ->when($codigo !== '', fn ($q) => $q->where('codigo', $codigo))
            ->when($codigo === '', fn ($q) => $q->where('cliente_id', $clienteId ?: -1))
            ->orderBy('id')
            ->get();

        return response()->json($creditos->map(fn ($c) => [
            'id'         => $c->id,
            'codigo'     => $c->codigo,
            'cliente_id' => $c->cliente_id,
            'cliente'    => $c->cliente_nome,
            'saldo'      => round((float) $c->saldo, 2),
        ])->values());
    }

    /**
     * Pagamento no convênio (fiado): confere o limite do cliente no SQLite do caixa e soma no "usado"
     * (dentro da transação da venda). A conta a receber nasce no servidor quando a venda sobe.
     * Convênio não dá troco e exige o consumidor identificado (com cadastro já no servidor).
     */
    private function abaterConvenio(array $pagamentos, float $totalVenda, $clienteId): void
    {
        $valor = round((float) collect($pagamentos)->where('forma_pagamento', 'convenio')->sum('valor'), 2);

        if ($valor <= 0) {
            return;
        }

        if (!$clienteId || (int) $clienteId < 0) {
            throw new \Exception($clienteId
                ? 'Cliente do cadastro rápido ainda não tem limite de convênio. Use outra forma de pagamento.'
                : 'Identifique o consumidor (F4) para vender no convênio.');
        }

        $semTroco = round((float) collect($pagamentos)->whereIn('forma_pagamento', ['convenio', 'credito_loja'])->sum('valor'), 2);
        if ($semTroco - $totalVenda > 0.009) {
            throw new \Exception('O convênio não pode ser maior que o total da venda (convênio não dá troco).');
        }

        $db = DB::connection('sqlite_local');
        $convenio = $db->getSchemaBuilder()->hasTable('convenios_cache')
            ? $db->table('convenios_cache')->where('cliente_id', (int) $clienteId)->lockForUpdate()->first()
            : null;

        if (!$convenio || (float) $convenio->limite <= 0) {
            throw new \Exception('Este cliente não tem limite de convênio neste caixa. Se o limite foi concedido agora, use "Atualizar Caixa".');
        }

        $disponivel = round(max(0, (float) $convenio->limite - (float) $convenio->usado), 2);
        if ($valor - $disponivel > 0.009) {
            throw new \Exception(sprintf('Limite do convênio insuficiente: disponível R$ %s.', number_format($disponivel, 2, ',', '.')));
        }

        $db->table('convenios_cache')->where('cliente_id', (int) $clienteId)
            ->update(['usado' => round((float) $convenio->usado + $valor, 2), 'updated_at' => now()]);
    }

    /** Caixa: limite do convênio do cliente (limite, usado e disponível) — JSON. */
    public function convenioCliente(Request $request)
    {
        $clienteId = (int) $request->get('cliente_id', 0);
        $db = DB::connection('sqlite_local');

        $convenio = $clienteId > 0 && $db->getSchemaBuilder()->hasTable('convenios_cache')
            ? $db->table('convenios_cache')->where('cliente_id', $clienteId)->first()
            : null;

        if (!$convenio || (float) $convenio->limite <= 0) {
            return response()->json(['tem_limite' => false, 'cliente_id' => $clienteId]);
        }

        return response()->json([
            'tem_limite' => true,
            'cliente_id' => $clienteId,
            'cliente'    => $convenio->cliente_nome,
            'limite'     => round((float) $convenio->limite, 2),
            'usado'      => round((float) $convenio->usado, 2),
            'disponivel' => round(max(0, (float) $convenio->limite - (float) $convenio->usado), 2),
            'dias'       => (int) $convenio->dias,
        ]);
    }

    /**
     * Caixa: convênios (vendas fiado) em aberto — os do servidor (cache) e as vendas deste caixa
     * que ainda não subiram. Filtra por período da venda, nº do cupom e cliente.
     */
    public function conveniosAbertos(Request $request)
    {
        $db = DB::connection('sqlite_local');
        if (!$db->getSchemaBuilder()->hasTable('convenios_abertos_cache')) {
            return response()->json(['linhas' => [], 'total' => 0, 'quantidade' => 0]);
        }

        $inicio = $request->filled('data_inicio') ? (string) $request->get('data_inicio') : null;
        $fim = $request->filled('data_fim') ? (string) $request->get('data_fim') : null;
        $cupom = (int) preg_replace('/\D/', '', (string) $request->get('cupom', ''));
        $termo = mb_strtolower(trim((string) $request->get('cliente', '')));
        $hoje = today()->toDateString();

        $servidor = $db->table('convenios_abertos_cache')->get();
        $uuidsServidor = $servidor->pluck('venda_uuid')->filter()->flip();

        $linhas = $servidor->map(fn ($c) => [
            'cliente'     => $c->cliente_nome,
            'numero_nfce' => $c->numero_nfce,
            'serie_nfce'  => $c->serie_nfce,
            'data'        => $c->data_venda,
            'vencimento'  => $c->vencimento,
            'valor'       => round((float) $c->valor, 2),
            'saldo'       => round((float) $c->saldo, 2),
            'situacao'    => $c->vencimento && $c->vencimento < $hoje ? 'vencido' : 'aberto',
        ]);

        // Vendas deste caixa no convênio que ainda não estão na lista do servidor: as não enviadas e as
        // enviadas depois da última atualização da lista (as mais antigas já quitadas não voltam)
        $nomes = $db->table('convenios_cache')->pluck('cliente_nome', 'cliente_id');
        $ultimaLista = $db->table('convenios_cache')->max('updated_at');
        $locais = $db->table('vendas_pendentes')
            ->where('status', '!=', 'cancelada')
            ->where(fn ($q) => $q->where('status', '!=', 'sincronizada')
                ->when($ultimaLista, fn ($q) => $q->orWhere('sincronizada_em', '>', $ultimaLista)))
            ->where(fn ($q) => $q->whereNull('status_fiscal')->orWhere('status_fiscal', '!=', 'cancelada'))
            ->where('pagamentos', 'like', '%convenio%')
            ->get()
            ->reject(fn ($v) => isset($uuidsServidor[$v->uuid]))
            ->map(function ($v) use ($nomes) {
                $valor = collect(json_decode((string) $v->pagamentos, true) ?? [])->where('forma_pagamento', 'convenio')->sum('valor');

                return [
                    'cliente'     => $nomes[$v->cliente_id] ?? ('Cliente #' . $v->cliente_id),
                    'numero_nfce' => $v->numero_nfce,
                    'serie_nfce'  => $v->serie_nfce,
                    'data'        => substr((string) ($v->vendida_em ?? $v->created_at), 0, 10),
                    'vencimento'  => null,
                    'valor'       => round((float) $valor, 2),
                    'saldo'       => round((float) $valor, 2),
                    'situacao'    => $v->status === 'sincronizada' ? 'aberto' : 'nao_sincronizado',
                ];
            })
            ->filter(fn ($l) => $l['valor'] > 0);

        $filtradas = $linhas->concat($locais)
            ->filter(fn ($l) => !$inicio || ($l['data'] && $l['data'] >= $inicio))
            ->filter(fn ($l) => !$fim || ($l['data'] && $l['data'] <= $fim))
            ->filter(fn ($l) => !$cupom || (int) $l['numero_nfce'] === $cupom)
            ->filter(fn ($l) => $termo === '' || str_contains(mb_strtolower((string) $l['cliente']), $termo))
            ->sortByDesc(fn ($l) => ($l['data'] ?? '') . sprintf('%09d', (int) $l['numero_nfce']))
            ->values();

        return response()->json([
            'linhas'     => $filtradas->take(300)->values(),
            'total'      => round((float) $filtradas->sum('saldo'), 2),
            'quantidade' => $filtradas->count(),
        ]);
    }

    public function finalizar(Request $request)
    {
        // 1. Lê o ID do pedido da sessão ANTES de ela ser limpa (previne adulteração via request)
        $pedidoVendaId = session('venda_carrinho.pedido_venda_id');

        $validado = $request->validate([
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|integer',
            'itens.*.produto_variante_id' => 'nullable|integer',
            'itens.*.quantidade' => 'required|integer|min:1',
            'itens.*.desconto' => 'nullable|numeric|min:0',
            'pagamentos' => 'required|array|min:1',
            'pagamentos.*.forma_pagamento' => 'required|in:dinheiro,pix,credito,debito,credito_loja,convenio',
            'pagamentos.*.valor' => 'required|numeric|min:0.01',
            'pagamentos.*.credito_loja_id' => 'required_if:pagamentos.*.forma_pagamento,credito_loja|nullable|integer',
            'desconto_global' => 'nullable|numeric|min:0',
            'cliente_id' => 'nullable|integer',
            'cpf_na_nota' => 'nullable|digits:11',
        ]);

        $caixa = CaixaLocal::aberto(Auth::id());

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
            $itensGravados = [];

            // Baixa de estoque, conferências e gravação da venda pendente na MESMA transação:
            // se qualquer conferência falhar, a baixa de estoque é desfeita junto.
            DB::connection('sqlite_local')->transaction(function () use ($validado, $caixa, $uuid, &$totalComDesconto, &$itensGravados, $pedidoVendaId) {
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

                $this->abaterCreditoLoja($validado['pagamentos'], $totalComDesconto, $validado['cliente_id'] ?? null);
                $this->abaterConvenio($validado['pagamentos'], $totalComDesconto, $validado['cliente_id'] ?? null);


                if ($pedidoVendaId) {
                    $db = DB::connection('sqlite_local');

                    if (! $db->table('pedidos_venda_cache')->where('id', $pedidoVendaId)->exists()) {
                        throw new \Exception('Pedido não encontrado no caixa. Use "Atualizar Caixa" e tente de novo.');
                    }

                    $jaUsado = $db->table('vendas_pendentes')
                        ->where('pedido_venda_id', $pedidoVendaId)
                        ->where('status_fiscal', '!=', 'cancelada')
                        ->exists();

                    if ($jaUsado) {
                        throw new \Exception('Este pedido já foi emitido neste caixa.');
                    }
                }

                // Id negativo = cliente do cadastro rápido, ainda só neste caixa (clientes_pendentes).
                // A sincronização troca pelo id do servidor quando o cliente sobe.
                $clienteId = $validado['cliente_id'] ?? null;
                $clienteLocalId = null;

                if ($clienteId !== null && $clienteId < 0) {
                    $pendente = DB::connection('sqlite_local')->table('clientes_pendentes')->where('id', -$clienteId)->first();

                    if (!$pendente) {
                        throw new \Exception('Consumidor não encontrado neste caixa. Busque o cliente de novo.');
                    }

                    // Se ele já subiu enquanto a venda estava na tela, usa direto o id do servidor
                    $clienteId = $pendente->id_central;
                    $clienteLocalId = $pendente->id_central ? null : $pendente->id;
                }

                DB::connection('sqlite_local')->table('vendas_pendentes')->insert([
                    'uuid' => $uuid,
                    'pedido_venda_id' => $pedidoVendaId, // <--- Vinculando o ID seguro da sessão aqui
                    'caixa_id_central' => $caixa->id_central,
                    'caixa_uuid' => $caixa->uuid,
                    'operador_id_central' => Auth::id(),
                    'cliente_id' => $clienteId,
                    'cliente_local_id' => $clienteLocalId,
                    'cpf_na_nota' => $validado['cpf_na_nota'] ?? null,
                    'total' => $totalComDesconto,
                    'troco' => $troco,
                    'desconto' => $descontoTotal,
                    'forma_pagamento' => null,
                    'pagamentos' => json_encode($validado['pagamentos']),
                    'itens' => json_encode($itensParaSalvar),
                    'status' => 'pendente_sync',
                    'pedido_venda_id' => $pedidoVendaId,
                    'vendida_em' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $itensGravados = $itensParaSalvar;
            });

            // Relatório de fechamento: descontos da venda e quem liberou (antes de consumir as autorizações)
            $this->registrarDescontos($uuid, $caixa, $itensGravados, (float) ($validado['desconto_global'] ?? 0));

            // Venda gravada: encerra o carrinho e a autorização de desconto desta venda
            session()->forget('venda_carrinho');
            AutorizacaoSupervisor::consumir('desconto_item');
            AutorizacaoSupervisor::consumir('desconto_global');

            $emissao = ['sucesso' => false, 'contingencia' => false, 'erro' => null];

            if (EmissaoLocal::ativa((int) $caixa->pdv_id)) {
                // O caixa emite a NFC-e sozinho, sem depender do servidor. A venda sobe pelo agendador
                // (a cada minuto) ou pelo Atualizar Caixa: a tela não espera pela rede
                $emissao = $this->emitirNoCaixa($uuid);
            } elseif (CentralStatus::fora()) {
                // PDV ainda emitindo pelo servidor e ele está fora: a venda fica no caixa
                $emissao['erro'] = 'Servidor indisponível: a venda foi salva no caixa e será enviada quando a conexão voltar.';
            } else {
                try {
                    (new SyncService())->enviarVendasPendentes();

                    if (CentralStatus::fora()) {
                        $emissao['erro'] = 'Servidor indisponível: a venda foi salva no caixa e será enviada quando a conexão voltar.';
                    } else {
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
                    }
                } catch (\Throwable $e) {
                    if (CentralStatus::erroDeConexao($e)) {
                        CentralStatus::marcarFora();
                        $emissao['erro'] = 'Servidor indisponível: a venda foi salva no caixa e será enviada quando a conexão voltar.';
                    }
                }
            }

            return response()->json([
                'sucesso' => true,
                'venda_uuid' => $uuid,
                'total' => $totalComDesconto,
                'emissao' => $emissao,
                'offline' => CentralStatus::fora(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        }
    }
}