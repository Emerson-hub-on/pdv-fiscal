<?php

namespace App\Http\Controllers;

use App\Models\Comanda;
use App\Models\ComandaImpressora;
use App\Models\ComandaPedido;
use App\Services\ComandaService;
use App\Services\ImpressaoComandaService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Comandas → Pedidos → Lista de Pedidos: os pedidos chegando dos garçons em três colunas
 * (Em preparo, Pronto / A caminho, Concluído). O operador marca o pedido como pronto (o garçom
 * é avisado na tela dele), entregue ou cancelado, e pode fechar a conta da mesa no balcão.
 * A tela se atualiza sozinha (dados()) e avisa quando chega pedido novo.
 * Os JSON validam à mão: o sistema só responde erro em JSON para api/* (bootstrap/app.php).
 */
class PedidoComandaController extends Controller
{
    /** Quantos concluídos/cancelados a coluna Concluído mostra (os mais recentes). */
    private const LIMITE_CONCLUIDOS = 60;

    public function __construct(private ComandaService $comandas)
    {
    }

    public function index()
    {
        $formas = $this->comandas->formasPagamento();

        return view('comandas.pedidos.index', ['dados' => $this->colunas(), 'formas' => $formas]);
    }

    public function dados(): JsonResponse
    {
        return response()->json($this->colunas());
    }

    public function mudarStatus(Request $request, ComandaPedido $pedido): JsonResponse
    {
        try {
            $this->comandas->mudarStatusPedido($pedido, (string) $request->input('status'), $request->user()->id);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->colunas());
    }

    /** Conta da mesa para fechar no balcão. */
    public function conta(Comanda $comanda): JsonResponse
    {
        if ($comanda->status === Comanda::ABERTA) {
            $comanda->recalcularTotal();
        }

        return response()->json(['conta' => $comanda->paraFechamento()]);
    }

    public function fecharConta(Request $request, Comanda $comanda): JsonResponse
    {
        try {
            $conta = $this->comandas->fecharConta($comanda, (array) $request->input('pagamentos', []), $request->user()->id, 'painel');
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['conta' => $conta->paraFechamento()] + $this->colunas());
    }

    /** Reimprime o pedido em todas as impressoras do setor dele, ou só na escolhida. */
    public function imprimir(Request $request, ComandaPedido $pedido, ImpressaoComandaService $impressao): JsonResponse
    {
        $somente = null;
        if ($request->filled('impressora_id')) {
            $somente = ComandaImpressora::find($request->integer('impressora_id'));
            if (!$somente) {
                return response()->json(['message' => 'Impressora não encontrada.'], 422);
            }
        } elseif (!ComandaImpressora::where('ativo', true)->exists()) {
            return response()->json(['message' => 'Nenhuma impressora ativa. Configure pelo ícone de impressora no topo da tela.'], 422);
        }

        $resultados = $impressao->imprimirPedido($pedido, $somente, true);

        if (!$resultados) {
            return response()->json(['message' => 'Nenhum item deste pedido é das categorias dessa(s) impressora(s).'], 422);
        }

        $falhas = collect($resultados)->where('ok', false);
        $ok = collect($resultados)->where('ok', true)->pluck('impressora');

        return response()->json([
            'message' => ($ok->isNotEmpty() ? 'Impresso em: ' . $ok->implode(', ') . '.' : '')
                . ($falhas->isNotEmpty() ? ' Falhou: ' . $falhas->map(fn ($f) => "{$f['impressora']} ({$f['erro']})")->implode('; ') : ''),
            'erro' => $falhas->isNotEmpty(),
        ] + $this->colunas());
    }

    private function colunas(): array
    {
        // A conta (comanda) vem com quantos pedidos válidos tem: o pagamento é da conta inteira, não de cada pedido
        $com = ['mesa', 'garcom:id,name', 'itens',
            'comanda' => fn ($q) => $q->withCount(['pedidos as pedidos_na_conta' => fn ($p) => $p->where('status', '!=', ComandaPedido::CANCELADO)]),
            'comanda.pagamentos'];

        // Em preparo: o mais antigo primeiro (ordem de chegada da cozinha)
        $preparo = ComandaPedido::with($com)->where('status', ComandaPedido::PREPARO)
            ->orderBy('enviado_em')->orderBy('id')->get();

        // Pronto / A caminho: prontos e entregues (conta ainda não paga)
        $pronto = ComandaPedido::with($com)->whereIn('status', [ComandaPedido::PRONTO, ComandaPedido::ENTREGUE])
            ->orderByRaw('COALESCE(pronto_em, entregue_em, enviado_em)')->orderBy('id')->get();

        // Concluído: pagos e cancelados, os mais recentes primeiro
        $concluido = ComandaPedido::with($com)->whereIn('status', [ComandaPedido::CONCLUIDO, ComandaPedido::CANCELADO])
            ->orderByRaw('COALESCE(concluido_em, cancelado_em) DESC')->orderByDesc('id')
            ->limit(self::LIMITE_CONCLUIDOS)->get();

        return [
            'agora'    => now()->toIso8601String(),
            'ultimo_id' => (int) ComandaPedido::max('id'),
            'colunas'  => [
                'preparo'   => $preparo->map->paraLista()->values(),
                'pronto'    => $pronto->map->paraLista()->values(),
                'concluido' => $concluido->map->paraLista()->values(),
            ],
            'totais'   => [
                'preparo'   => $preparo->count(),
                'pronto'    => $pronto->count(),
                'concluido' => ComandaPedido::whereIn('status', [ComandaPedido::CONCLUIDO, ComandaPedido::CANCELADO])->count(),
            ],
        ];
    }
}
