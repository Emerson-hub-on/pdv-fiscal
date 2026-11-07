<?php

namespace App\Http\Controllers;

use App\Models\CardapioCategoria;
use App\Models\CardapioItem;
use App\Models\ComandaPedido;
use App\Models\Mesa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Tela do garçom (login "Garçom", guard "garcom"): roda no celular/tablet ligado ao servidor.
 * Abas Cardápio (itens ativos de Comandas → Cardápio → Menu, por categoria) e Mesas
 * (vazia por enquanto: o cadastro de mesas é o próximo passo).
 */
class GarcomController extends Controller
{
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

        return view('garcom.index', compact('itens', 'categorias', 'temSemCategoria', 'garcom', 'mesas'));
    }

    /** Mesas ativas em JSON: a tela do garçom atualiza a situação delas sozinha (outros garçons mudam também). */
    public function mesas(): JsonResponse
    {
        return response()->json([
            'agora' => now()->toIso8601String(),
            'mesas' => $this->mesasAtivas(),
        ]);
    }

    /** O garçom muda a situação da mesa (disponível / aguardando pedido / pedido entregue). */
    public function mudarStatusMesa(Request $request, Mesa $mesa): JsonResponse
    {
        // Validação manual: o sistema só responde erro em JSON para api/* (bootstrap/app.php)
        $status = (string) $request->input('status');

        if (!array_key_exists($status, Mesa::STATUS)) {
            return response()->json(['message' => 'Situação inválida.'], 422);
        }

        if (!$mesa->ativo) {
            return response()->json(['message' => 'Esta mesa foi inativada no painel.'], 422);
        }

        $mesa->mudarStatus($status, Auth::guard('garcom')->id());

        return response()->json(['mesa' => $this->mesaParaTela($mesa)]);
    }

    /**
     * "Finalizar pedido": grava o pedido da mesa com os itens da comanda montada no celular.
     * Nome e preço vêm do cadastro (o celular manda só o item, a quantidade e a observação).
     * Reenviar o mesmo uuid (toque duplo, conexão caiu no meio) devolve o pedido já gravado.
     */
    public function enviarPedido(Request $request, Mesa $mesa): JsonResponse
    {
        // Validação manual: o sistema só responde erro em JSON para api/* (bootstrap/app.php)
        $validador = Validator::make($request->all(), [
            'uuid'                      => ['required', 'uuid'],
            'itens'                     => ['required', 'array', 'min:1', 'max:100'],
            'itens.*.cardapio_item_id'  => ['required', 'integer'],
            'itens.*.quantidade'        => ['required', 'integer', 'min:1', 'max:999'],
            'itens.*.observacao'        => ['nullable', 'string', 'max:200'],
        ], [
            'itens.required' => 'A comanda está vazia.',
            'itens.min'      => 'A comanda está vazia.',
            'itens.*.quantidade.max' => 'Quantidade máxima por item: 999.',
            'itens.*.observacao.max' => 'A observação pode ter no máximo 200 caracteres.',
        ]);

        if ($validador->fails()) {
            return response()->json(['message' => $validador->errors()->first()], 422);
        }

        if (!$mesa->ativo) {
            return response()->json(['message' => 'Esta mesa foi inativada no painel.'], 422);
        }

        $dados = $validador->validated();

        if ($jaGravado = ComandaPedido::where('uuid', $dados['uuid'])->first()) {
            return response()->json(['pedido_id' => $jaGravado->id, 'mesa' => $this->mesaParaTela($jaGravado->mesa)]);
        }

        // Itens que o garçom pode oferecer agora (ativos, de produto ativo)
        $cardapio = CardapioItem::with(['produto', 'variante'])
            ->whereIn('id', collect($dados['itens'])->pluck('cardapio_item_id'))
            ->where('ativo', true)
            ->whereHas('produto', fn ($p) => $p->where('ativo', true))
            ->get()
            ->keyBy('id');

        $faltando = collect($dados['itens'])->pluck('cardapio_item_id')->reject(fn ($id) => $cardapio->has($id));
        if ($faltando->isNotEmpty()) {
            return response()->json([
                'message' => 'Algum item da comanda saiu do cardápio. Atualize a tela, confira a comanda e envie de novo.',
            ], 422);
        }

        $pedido = DB::transaction(function () use ($dados, $mesa, $cardapio) {
            $pedido = ComandaPedido::create([
                'uuid'       => $dados['uuid'],
                'mesa_id'    => $mesa->id,
                'garcom_id'  => Auth::guard('garcom')->id(),
                'enviado_em' => now(),
            ]);

            $total = 0;
            foreach ($dados['itens'] as $linha) {
                $item = $cardapio[$linha['cardapio_item_id']];
                $preco = round((float) $item->produto->preco_venda, 2);
                $subtotal = round($preco * $linha['quantidade'], 2);
                $total += $subtotal;

                $pedido->itens()->create([
                    'cardapio_item_id'    => $item->id,
                    'produto_id'          => $item->produto_id,
                    'produto_variante_id' => $item->produto_variante_id,
                    'nome'                => $item->nomeExibido(),
                    'quantidade'          => $linha['quantidade'],
                    'preco_unitario'      => $preco,
                    'subtotal'            => $subtotal,
                    'observacao'          => trim((string) ($linha['observacao'] ?? '')) ?: null,
                ]);
            }

            $pedido->update(['total' => round($total, 2)]);

            // Mesa passa a "Aguardando pedido" (se já estava, o relógio dela continua; o do pedido começa agora)
            $mesa->mudarStatus(Mesa::AGUARDANDO, Auth::guard('garcom')->id());

            return $pedido;
        });

        return response()->json(['pedido_id' => $pedido->id, 'mesa' => $this->mesaParaTela($mesa)]);
    }

    private function mesaParaTela(Mesa $mesa): array
    {
        return $mesa->fresh(['alteradoPor:id,name', 'pedidosAbertos.itens', 'pedidosAbertos.garcom:id,name'])->paraTela();
    }

    private function mesasAtivas(): array
    {
        return Mesa::with(['alteradoPor:id,name', 'pedidosAbertos.itens', 'pedidosAbertos.garcom:id,name'])
            ->where('ativo', true)
            ->ordenadas()
            ->get()
            ->map(fn (Mesa $m) => $m->paraTela())
            ->all();
    }

    /** Foto do item do cardápio para a tela do garçom (a rota do painel exige login do painel). */
    public function imagem(CardapioItem $item): BinaryFileResponse
    {
        abort_unless($item->ativo && $item->imagem && Storage::disk('local')->exists($item->imagem), 404);

        return response()->file(Storage::disk('local')->path($item->imagem), [
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
