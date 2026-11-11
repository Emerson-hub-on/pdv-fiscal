<?php

namespace App\Http\Controllers;

use App\Models\Comanda;
use App\Models\ComandaImpressora;
use App\Models\ComandaPedido;
use App\Models\IfoodConfiguracao;
use App\Services\ComandaService;
use App\Services\IfoodService;
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
        // Com a tela aberta, busca os pedidos do iFood a cada 30 s ANTES de responder: o pedido novo já vem
        // nesta atualização (antes só aparecia na seguinte, até 8 s depois). Fora do intervalo, polling() volta na hora.
        $cfg = IfoodConfiguracao::atual();
        if ($cfg->ativo && $cfg->credenciaisPreenchidas()) {
            app(IfoodService::class)->polling($cfg);
        }

        return response()->json($this->colunas());
    }

    public function mudarStatus(Request $request, ComandaPedido $pedido): JsonResponse
    {
        $status = (string) $request->input('status');

        try {
            // Pedido do iFood: avisa o iFood antes de mudar aqui
            if ($pedido->origem === 'ifood' && $pedido->ifood) {
                if ($status === ComandaPedido::CANCELADO) {
                    throw new DomainException('Pedido do iFood: use o botão Cancelar para escolher o motivo.');
                }
                if ($pedido->ifood->cancelamento_solicitado_em && $pedido->ifood->status !== \App\Models\IfoodPedido::CANCELLED) {
                    throw new DomainException('Cancelamento pedido ao iFood: aguarde a resposta antes de mudar a situação.');
                }
                if ($status === ComandaPedido::PRONTO) {
                    app(IfoodService::class)->marcarPronto(IfoodConfiguracao::atual(), $pedido->ifood);
                }
                // Entrega própria: com o código do cliente, o iFood conclui o pedido lá também
                // (sem o código, finaliza só aqui: o iFood conclui sozinho mais tarde)
                $codigo = trim((string) $request->input('codigo_entrega'));
                if ($status === ComandaPedido::ENTREGUE && $codigo !== '') {
                    app(IfoodService::class)->confirmarEntrega(IfoodConfiguracao::atual(), $pedido->ifood, $codigo);
                }
            }

            $this->comandas->mudarStatusPedido($pedido, $status, $request->user()->id);

            // Pedido do iFood não tem conta de mesa para fechar: entregue já é finalizado (vai para Concluído)
            if ($pedido->origem === 'ifood' && $status === ComandaPedido::ENTREGUE) {
                $pedido->refresh()->mudarStatus(ComandaPedido::CONCLUIDO, $request->user()->id);
            }
        } catch (DomainException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->colunas());
    }

    /** Aceita o pedido do iFood (aceite manual). */
    public function aceitarIfood(ComandaPedido $pedido, IfoodService $ifood): JsonResponse
    {
        if (!$pedido->ifood) {
            return response()->json(['message' => 'Este pedido não é do iFood.'], 422);
        }

        try {
            $ifood->aceitar(IfoodConfiguracao::atual(), $pedido->ifood);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => "Pedido iFood #{$pedido->ifood->display_id} aceito."] + $this->colunas());
    }

    public function motivosIfood(ComandaPedido $pedido, IfoodService $ifood): JsonResponse
    {
        if (!$pedido->ifood) {
            return response()->json(['message' => 'Este pedido não é do iFood.'], 422);
        }

        try {
            return response()->json(['motivos' => $ifood->motivosCancelamento(IfoodConfiguracao::atual(), $pedido->ifood)]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function cancelarIfood(Request $request, ComandaPedido $pedido, IfoodService $ifood): JsonResponse
    {
        if (!$pedido->ifood) {
            return response()->json(['message' => 'Este pedido não é do iFood.'], 422);
        }
        $codigo = trim((string) $request->input('codigo'));
        $motivo = trim((string) $request->input('motivo'));
        if ($codigo === '' || $motivo === '') {
            return response()->json(['message' => 'Escolha o motivo do cancelamento.'], 422);
        }

        try {
            $ifood->cancelar(IfoodConfiguracao::atual(), $pedido->ifood, $codigo, $motivo);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => "Cancelamento do pedido iFood #{$pedido->ifood->display_id} enviado. Aguardando a confirmação do iFood."] + $this->colunas());
    }

    /** Responde ao pedido de cancelamento / reembolso feito pelo cliente no iFood. */
    public function disputaIfood(Request $request, ComandaPedido $pedido, IfoodService $ifood): JsonResponse
    {
        if (!$pedido->ifood) {
            return response()->json(['message' => 'Este pedido não é do iFood.'], 422);
        }
        $aceitar = $request->input('acao') === 'aceitar';
        if (!$aceitar && $request->input('acao') !== 'recusar') {
            return response()->json(['message' => 'Escolha aceitar ou recusar.'], 422);
        }

        try {
            $ifood->responderDisputa(IfoodConfiguracao::atual(), $pedido->ifood, $aceitar, $request->input('motivo'));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => $aceitar
            ? "Pedido do cliente aceito (iFood #{$pedido->ifood->display_id}). O iFood vai atualizar o pedido."
            : "Pedido do cliente recusado (iFood #{$pedido->ifood->display_id})."] + $this->colunas());
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
            return response()->json(['message' => $this->motivoSemImpressora($pedido, $somente)], 422);
        }

        $falhas = collect($resultados)->where('ok', false);
        $ok = collect($resultados)->where('ok', true)->pluck('impressora');

        return response()->json([
            'message' => ($ok->isNotEmpty() ? 'Impresso em: ' . $ok->implode(', ') . '.' : '')
                . ($falhas->isNotEmpty() ? ' Falhou: ' . $falhas->map(fn ($f) => "{$f['impressora']} ({$f['erro']})")->implode('; ') : ''),
            'erro' => $falhas->isNotEmpty(),
        ] + $this->colunas());
    }

    /** Explica por que nada saiu: de que categorias são os itens e o que cada impressora imprime. */
    private function motivoSemImpressora(ComandaPedido $pedido, ?ComandaImpressora $somente): string
    {
        $categorias = \App\Models\CardapioItem::with('categoria:id,nome')
            ->whereIn('id', $pedido->itens->pluck('cardapio_item_id')->filter())->get()
            ->map(fn ($i) => $i->categoria?->nome ?? 'sem categoria')->unique()->implode(', ');

        $impressoras = ($somente ? collect([$somente]) : ComandaImpressora::with('categorias')->where('ativo', true)->orderBy('nome')->get())
            ->map(fn ($i) => $i->nome . ' imprime só ' . ($i->categorias->pluck('nome')->when($i->sem_categoria, fn ($c) => $c->push('sem categoria'))->implode(', ') ?: 'nada'))
            ->implode('; ');

        return "Nenhuma impressora recebe os itens deste pedido. Itens de: {$categorias}. {$impressoras}. "
            . 'Ajuste em Impressoras (marque essas categorias ou "Pedido completo").';
    }

    private function colunas(): array
    {
        // A conta (comanda) vem com quantos pedidos válidos tem: o pagamento é da conta inteira, não de cada pedido
        $com = ['mesa', 'garcom:id,name', 'itens', 'ifood',
            // Cliente do iFood com quantos pedidos (não cancelados) já fez: "3º pedido" no cartão
            'ifood.cliente' => fn ($q) => $q->withCount(['pedidos as pedidos_validos' => fn ($p) => $p->where('status', '!=', \App\Models\IfoodPedido::CANCELLED)]),
            'comanda' => fn ($q) => $q->withCount([
                'pedidos as pedidos_na_conta' => fn ($p) => $p->where('status', '!=', ComandaPedido::CANCELADO),
                'pedidos as pedidos_em_preparo' => fn ($p) => $p->where('status', ComandaPedido::PREPARO),
            ]),
            'comanda.pagamentos'];

        // Em todas as colunas, o mais novo fica no topo e o mais antigo no final
        // Em preparo: pela chegada do pedido
        $preparo = ComandaPedido::with($com)->where('status', ComandaPedido::PREPARO)
            ->orderByDesc('enviado_em')->orderByDesc('id')->get();

        // Pronto / A caminho: prontos e entregues (conta ainda não paga), pela hora em que ficou pronto
        $pronto = ComandaPedido::with($com)->whereIn('status', [ComandaPedido::PRONTO, ComandaPedido::ENTREGUE])
            ->orderByRaw('COALESCE(pronto_em, entregue_em, enviado_em) DESC')->orderByDesc('id')->get();

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
