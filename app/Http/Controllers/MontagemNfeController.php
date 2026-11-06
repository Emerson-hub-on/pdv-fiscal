<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\EntradaNota;
use App\Models\PedidoVenda;
use App\Models\Venda;
use App\Services\MontagemNfeDevolucaoService;
use App\Services\MontagemNfePedidoService;
use App\Services\MontagemNfeService;
use Illuminate\Http\Request;

/** Faturamento → Montagem NF-e → A partir de NFC-e / A partir de pedido */
class MontagemNfeController extends Controller
{
    public function __construct(private MontagemNfeService $montagem)
    {
    }

    public function index(Request $request)
    {
        $modo = $request->get('modo') === 'data' ? 'data' : 'cupom';
        $data = $request->filled('data') && strtotime($request->get('data'))
            ? date('Y-m-d', strtotime($request->get('data')))
            : now()->toDateString();

        $vendas = collect();
        $pendentes = collect();
        $resultadoCupom = null;

        if ($modo === 'cupom') {
            if ($request->filled('serie') || $request->filled('numero')) {
                $dados = $request->validate([
                    'serie'  => ['required', 'integer', 'min:0', 'max:999'],
                    'numero' => ['required', 'integer', 'min:1', 'max:999999999'],
                ], [], ['serie' => 'série', 'numero' => 'número do cupom']);

                $resultadoCupom = $this->montagem->buscarPorCupom((int) $dados['serie'], (int) $dados['numero']);

                if ($resultadoCupom['venda']) {
                    $vendas = collect([$resultadoCupom['venda']]);
                }
            }
        } else {
            $vendas = $this->montagem->buscarPorData($data);
            $pendentes = $this->montagem->pendentesPorData($data);
        }

        // Uma única série de NFC-e nos PDVs ativos: já vem preenchida
        $seriesPdv = \App\Models\Pdv::where('ativo', true)->distinct()->pluck('serie_nfce');
        $seriePadrao = $seriesPdv->count() === 1 ? $seriesPdv->first() : null;

        $bloqueios = $vendas->mapWithKeys(fn (Venda $v) => [$v->id => MontagemNfeService::erroVendaParaMontagem($v)]);
        $nfesGeradas = $vendas->mapWithKeys(fn (Venda $v) => [$v->id => MontagemNfeService::nfeJaGerada($v)]);

        return view('faturamento.montagem-nfe.nfce', compact(
            'modo', 'data', 'seriePadrao', 'vendas', 'pendentes', 'resultadoCupom', 'bloqueios', 'nfesGeradas'
        ));
    }

    /** Abre a tela da NF-e já preenchida com os dados da NFC-e; ao salvar vira rascunho. */
    public function montar(Venda $venda)
    {
        $erro = MontagemNfeService::erroVendaParaMontagem($venda);

        if ($erro) {
            return redirect()
                ->route('montagem-nfe.nfce.index', ['modo' => 'cupom', 'serie' => $venda->serie_nfce, 'numero' => $venda->numero_nfce])
                ->withErrors(['venda' => $erro]);
        }

        [$nota, $avisos] = $this->montagem->montar($venda);

        return $this->telaNota($nota, $avisos);
    }

    // ---------------- Faturamento → Montagem NF-e → A partir de pedido ----------------

    public function indexPedido(Request $request, MontagemNfePedidoService $montagemPedido)
    {
        $modo = $request->get('modo') === 'data' ? 'data' : 'numero';
        $data = $request->filled('data') && strtotime($request->get('data'))
            ? date('Y-m-d', strtotime($request->get('data')))
            : now()->toDateString();

        $pedidos = collect();
        $naoEncontrado = null;

        if ($modo === 'numero') {
            if ($request->filled('numero')) {
                $dados = $request->validate(['numero' => ['required', 'integer', 'min:1']], [], ['numero' => 'número do pedido']);
                $pedido = $montagemPedido->buscarPorNumero((int) $dados['numero']);

                $pedido
                    ? $pedidos = collect([$pedido])
                    : $naoEncontrado = "Nenhum pedido de venda encontrado com o número {$dados['numero']}.";
            }
        } else {
            $pedidos = $montagemPedido->buscarPorData($data);
        }

        $bloqueios = $pedidos->mapWithKeys(fn (PedidoVenda $p) => [$p->id => MontagemNfePedidoService::erroPedidoParaMontagem($p)]);
        $nfesGeradas = $pedidos->mapWithKeys(fn (PedidoVenda $p) => [$p->id => MontagemNfePedidoService::nfeDoPedido($p)]);
        $vendasCaixa = $pedidos->mapWithKeys(fn (PedidoVenda $p) => [$p->id => MontagemNfePedidoService::vendaDoPedido($p)]);

        return view('faturamento.montagem-nfe.pedido', compact(
            'modo', 'data', 'pedidos', 'naoEncontrado', 'bloqueios', 'nfesGeradas', 'vendasCaixa'
        ));
    }

    /** Abre a tela da NF-e já preenchida com os dados do pedido; ao salvar vira rascunho. */
    public function montarPedido(PedidoVenda $pedido, MontagemNfePedidoService $montagemPedido)
    {
        $erro = MontagemNfePedidoService::erroPedidoParaMontagem($pedido);

        if ($erro) {
            return redirect()
                ->route('montagem-nfe.pedido.index', ['modo' => 'numero', 'numero' => $pedido->numero])
                ->withErrors(['pedido' => $erro]);
        }

        [$nota, $avisos] = $montagemPedido->montarDoPedido($pedido);

        return $this->telaNota($nota, $avisos);
    }

    // ---------------- Faturamento → Montagem NF-e → NF-e Devolução ----------------

    public function indexDevolucao(Request $request, MontagemNfeDevolucaoService $devolucao)
    {
        $modo = $request->get('modo') === 'numero' ? 'numero' : 'data';
        $data = $request->filled('data') && strtotime($request->get('data'))
            ? date('Y-m-d', strtotime($request->get('data')))
            : now()->toDateString();

        $entradas = collect();
        $naoEncontrado = null;

        if ($modo === 'numero') {
            if ($request->filled('numero')) {
                $dados = $request->validate(['numero' => ['required', 'integer', 'min:1']], [], ['numero' => 'número da nota']);
                $entradas = $devolucao->buscarPorNumero((int) $dados['numero']);
                $naoEncontrado = $entradas->isEmpty() ? "Nenhuma nota de entrada encontrada com o número {$dados['numero']}." : null;
            }
        } else {
            $entradas = $devolucao->buscarPorData($data);
        }

        $bloqueios = $entradas->mapWithKeys(fn (EntradaNota $e) => [$e->id => MontagemNfeDevolucaoService::erroEntradaParaDevolucao($e)]);
        $devolucoes = $entradas->mapWithKeys(fn (EntradaNota $e) => [$e->id => MontagemNfeDevolucaoService::devolucoesDaEntrada($e)]);

        return view('faturamento.montagem-nfe.devolucao', compact(
            'modo', 'data', 'entradas', 'naoEncontrado', 'bloqueios', 'devolucoes'
        ));
    }

    /** Itens da entrada para a janela de seleção (JSON). */
    public function itensDevolucao(EntradaNota $entrada, MontagemNfeDevolucaoService $devolucao)
    {
        if ($erro = MontagemNfeDevolucaoService::erroEntradaParaDevolucao($entrada)) {
            return response()->json(['message' => $erro], 422);
        }

        return response()->json([
            'entrada' => [
                'id'         => $entrada->id,
                'numero'     => $entrada->numero,
                'serie'      => $entrada->serie,
                'fornecedor' => $entrada->fornecedor?->nome,
            ],
            'itens' => $devolucao->itensComSaldo($entrada)->map(fn ($l) => [
                'id'         => $l['item']->id,
                'n_item'     => $l['n_item'],
                'descricao'  => $l['item']->descricao,
                'unidade'    => $l['item']->produto?->unidade_comercial ?? $l['item']->unidade,
                'quantidade' => $l['quantidade'],
                'devolvido'  => $l['devolvido'],
                'saldo'      => $l['saldo'],
                'valor'      => round((float) $l['item']->valor_unitario / max((int) $l['item']->qtd_por_caixa, 1), 4),
                'com_st'     => $l['com_st'],
            ])->values(),
        ]);
    }

    /** Abre a tela da NF-e de devolução já montada com os itens escolhidos (?q[item_id]=quantidade). */
    public function montarDevolucao(Request $request, EntradaNota $entrada, MontagemNfeDevolucaoService $devolucao)
    {
        $voltar = fn ($erro) => redirect()
            ->route('montagem-nfe.devolucao.index', ['modo' => 'numero', 'numero' => $entrada->numero])
            ->withErrors(['devolucao' => $erro]);

        if ($erro = MontagemNfeDevolucaoService::erroEntradaParaDevolucao($entrada)) {
            return $voltar($erro);
        }

        $quantidades = collect((array) $request->input('q', []))
            ->mapWithKeys(fn ($qtd, $id) => [(int) $id => (float) str_replace(',', '.', (string) $qtd)])
            ->filter(fn ($qtd) => $qtd > 0)
            ->all();

        if (!$quantidades) {
            return $voltar('Selecione ao menos um item para devolver.');
        }

        [$nota, $avisos] = $devolucao->montar($entrada, $quantidades);

        if ($nota->itens->isEmpty()) {
            return $voltar('Os itens escolhidos não têm saldo para devolver (já foram devolvidos em outra NF-e).');
        }

        return $this->telaNota($nota, $avisos);
    }

    private function telaNota($nota, array $avisos)
    {
        return view('notasfiscais.create', [
            'clientes'       => Cliente::ativos()->orderBy('nome')->get(),
            'crtEmpresa'     => Empresa::first()->crt,
            'notaFiscal'     => null,
            'notaPreMontada' => $nota,
            'avisosMontagem' => $avisos,
        ]);
    }
}
