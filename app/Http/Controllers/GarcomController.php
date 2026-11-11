<?php

namespace App\Http\Controllers;

use App\Models\CardapioCategoria;
use App\Models\CardapioItem;
use App\Models\Comanda;
use App\Models\ComandaPedido;
use App\Models\Mesa;
use App\Services\ComandaService;
use App\Services\ImpressaoComandaService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Tela do garçom (login "Garçom", guard "garcom"): roda no celular/tablet ligado ao servidor.
 * Mesas → escolhe a mesa; Cardápio → monta o pedido; Ver Comanda → finaliza o pedido;
 * Fechar conta → revisa os itens da mesa, recebe o pagamento (uma ou mais formas) e libera a mesa.
 * Os JSON validam à mão: o sistema só responde erro em JSON para api/* (bootstrap/app.php).
 */
class GarcomController extends Controller
{
    public function __construct(private ComandaService $comandas)
    {
    }

    public function index()
    {
        // Só o que o garçom pode oferecer: item ativo de produto ativo (variante que ainda existe)
        $itens = CardapioItem::with(['produto', 'variante', 'categoria'])
            ->where('ativo', true)
            ->whereHas('produto', fn ($p) => $p->where('ativo', true))
            ->where(fn ($q) => $q->whereNull('produto_variante_id')->orWhereHas('variante'))
            ->get()
            // Na ordem do cardápio: categoria (ordem dela), depois a ordem do item; sem categoria por último
            ->sortBy(fn (CardapioItem $i) => [
                $i->categoria ? 0 : 1,
                $i->categoria?->ordem ?? 0,
                $i->categoria?->nome ?? '',
                $i->ordem,
                $i->id,
            ])
            ->values();

        // Só as categorias que têm item para mostrar
        $categorias = CardapioCategoria::ordenadas()
            ->whereIn('id', $itens->pluck('cardapio_categoria_id')->filter()->unique())
            ->get();

        $temSemCategoria = $itens->contains(fn ($i) => !$i->cardapio_categoria_id);
        $garcom = Auth::guard('garcom')->user();
        $mesas = $this->mesasAtivas();
        $formas = $this->comandas->formasPagamento();

        return view('garcom.index', compact('itens', 'categorias', 'temSemCategoria', 'garcom', 'mesas', 'formas'));
    }

    /** Mesas ativas em JSON: a tela do garçom atualiza sozinha (outros garçons e a cozinha mudam também). */
    public function mesas(): JsonResponse
    {
        return response()->json([
            'agora' => now()->toIso8601String(),
            'mesas' => $this->mesasAtivas(),
        ]);
    }

    /** "Finalizar pedido": grava o pedido da mesa com os itens da comanda montada no celular. */
    public function enviarPedido(Request $request, Mesa $mesa): JsonResponse
    {
        $validador = Validator::make($request->all(), [
            'uuid'                      => ['required', 'uuid'],
            'cliente'                   => ['nullable', 'string', 'max:60'],
            'itens'                     => ['required', 'array', 'min:1', 'max:100'],
            'itens.*.cardapio_item_id'  => ['required', 'integer'],
            'itens.*.quantidade'        => ['required', 'integer', 'min:1', 'max:999'],
            'itens.*.observacao'        => ['nullable', 'string', 'max:200'],
        ], [
            'itens.required'         => 'A comanda está vazia.',
            'itens.min'              => 'A comanda está vazia.',
            'cliente.max'            => 'O nome do cliente pode ter no máximo 60 caracteres.',
            'itens.*.quantidade.max' => 'Quantidade máxima por item: 999.',
            'itens.*.observacao.max' => 'A observação pode ter no máximo 200 caracteres.',
        ]);

        if ($validador->fails()) {
            return response()->json(['message' => $validador->errors()->first()], 422);
        }

        $dados = $validador->validated();

        try {
            $pedido = $this->comandas->registrarPedido(
                $mesa, $dados['uuid'], $dados['itens'], $dados['cliente'] ?? null, Auth::guard('garcom')->id()
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Pedido novo sai sozinho nas impressoras dos setores (depois da resposta: o garçom não espera a impressora)
        if ($pedido->wasRecentlyCreated) {
            $id = $pedido->id;
            defer(function () use ($id) {
                if ($p = ComandaPedido::find($id)) {
                    app(ImpressaoComandaService::class)->imprimirPedido($p);
                }
            });
        }

        return response()->json(['pedido_id' => $pedido->id, 'mesa' => $this->mesaParaTela($pedido->mesa)]);
    }

    /** Conta da mesa para a tela "Fechar conta": itens de todos os pedidos e o total. */
    public function conta(Mesa $mesa): JsonResponse
    {
        $conta = $mesa->contaAberta()->first();

        if (!$conta) {
            return response()->json(['message' => 'Esta mesa não tem conta aberta.'], 404);
        }

        $conta->recalcularTotal();

        return response()->json(['conta' => $conta->paraFechamento()]);
    }

    /** Fecha a conta da mesa com os pagamentos (uma ou mais formas) e libera a mesa. */
    public function fecharConta(Request $request, Mesa $mesa): JsonResponse
    {
        $conta = Comanda::where('mesa_id', $mesa->id)->find((int) $request->input('conta_id'));

        if (!$conta) {
            return response()->json(['message' => 'Conta não encontrada. Atualize a tela.'], 404);
        }

        try {
            $conta = $this->comandas->fecharConta($conta, (array) $request->input('pagamentos', []), Auth::guard('garcom')->id(), 'garcom', (array) $request->input('cliente', []));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['conta' => $conta->paraFechamento(), 'mesa' => $this->mesaParaTela($mesa)]);
    }

    /** Foto do item do cardápio para a tela do garçom (a rota do painel exige login do painel). */
    public function imagem(CardapioItem $item): BinaryFileResponse
    {
        abort_unless($item->ativo && $item->imagem && Storage::disk('local')->exists($item->imagem), 404);

        return response()->file(Storage::disk('local')->path($item->imagem), [
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }

    private const CARREGAR_MESA = ['alteradoPor:id,name', 'pedidosAbertos.itens', 'pedidosAbertos.garcom:id,name', 'contaAberta'];

    private function mesaParaTela(Mesa $mesa): array
    {
        return $mesa->fresh(self::CARREGAR_MESA)->paraTela();
    }

    private function mesasAtivas(): array
    {
        return Mesa::with(self::CARREGAR_MESA)
            ->where('ativo', true)
            ->ordenadas()
            ->get()
            ->map(fn (Mesa $m) => $m->paraTela())
            ->all();
    }
}
