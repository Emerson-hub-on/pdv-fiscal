<?php

namespace App\Services;

use App\Models\CardapioItem;
use App\Models\Comanda;
use App\Models\ComandaPedido;
use App\Models\FormaPagamentoCaixa;
use App\Models\Mesa;
use App\Support\FormasCaixa;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Regras das comandas (mesas), usadas pela tela do garçom e pela Lista de Pedidos do painel:
 * registrar o pedido na conta da mesa, mudar a situação do pedido e fechar a conta com os pagamentos.
 * Erros de regra saem como DomainException com a mensagem para o operador.
 */
class ComandaService
{
    /**
     * Grava o pedido montado pelo garçom na conta aberta da mesa (abre a conta se for o 1º).
     * Nome e preço vêm do cadastro. Reenviar o mesmo uuid devolve o pedido já gravado.
     *
     * @param array $itens [['cardapio_item_id' => int, 'quantidade' => int, 'observacao' => ?string], ...]
     */
    public function registrarPedido(Mesa $mesa, string $uuid, array $itens, ?string $cliente, ?int $garcomId): ComandaPedido
    {
        if ($jaGravado = ComandaPedido::where('uuid', $uuid)->first()) {
            return $jaGravado;
        }

        if (!$mesa->ativo) {
            throw new DomainException('Esta mesa foi inativada no painel.');
        }

        // Itens que o garçom pode oferecer agora (ativos, de produto ativo)
        $cardapio = CardapioItem::with(['produto', 'variante'])
            ->whereIn('id', collect($itens)->pluck('cardapio_item_id'))
            ->where('ativo', true)
            ->whereHas('produto', fn ($p) => $p->where('ativo', true))
            ->get()
            ->keyBy('id');

        if (collect($itens)->pluck('cardapio_item_id')->contains(fn ($id) => !$cardapio->has($id))) {
            throw new DomainException('Algum item da comanda saiu do cardápio. Atualize a tela, confira a comanda e envie de novo.');
        }

        return DB::transaction(function () use ($mesa, $uuid, $itens, $cliente, $garcomId, $cardapio) {
            $mesa = Mesa::whereKey($mesa->id)->lockForUpdate()->first(); // um pedido por vez na mesma mesa

            $conta = $mesa->contaAberta()->first() ?? Comanda::create([
                'mesa_id'   => $mesa->id,
                'status'    => Comanda::ABERTA,
                'aberta_em' => now(),
            ]);

            $pedido = ComandaPedido::create([
                'uuid'         => $uuid,
                'comanda_id'   => $conta->id,
                'origem'       => 'mesa',
                'status'       => ComandaPedido::PREPARO,
                'mesa_id'      => $mesa->id,
                'garcom_id'    => $garcomId,
                'cliente_nome' => trim((string) $cliente) ?: null,
                'enviado_em'   => now(),
            ]);

            $total = 0;
            foreach ($itens as $linha) {
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
            $conta->recalcularTotal();

            // Enviou para a cozinha: baixa o estoque (volta se o pedido for cancelado)
            app(EstoqueService::class)->baixarPedido($pedido);

            // Mesa passa a "Aguardando pedido" (se já estava, o relógio dela continua; o do pedido começa agora)
            $mesa->mudarStatus(Mesa::AGUARDANDO, $garcomId);

            return $pedido;
        });
    }

    /** Muda a situação de um pedido (Lista de Pedidos). Concluído só acontece pelo fechamento da conta. */
    public function mudarStatusPedido(ComandaPedido $pedido, string $status, ?int $userId): void
    {
        if (!in_array($status, ComandaPedido::STATUS_MANUAIS, true)) {
            throw new DomainException('Situação inválida.');
        }

        if (in_array($pedido->status, [ComandaPedido::CONCLUIDO, ComandaPedido::CANCELADO], true)) {
            throw new DomainException('Pedido ' . ComandaPedido::STATUS[$pedido->status]['rotulo'] . ': a situação não muda mais.');
        }

        if ($status === $pedido->status) {
            return;
        }

        DB::transaction(function () use ($pedido, $status, $userId) {
            $pedido->mudarStatus($status, $userId);

            if ($status === ComandaPedido::CANCELADO) {
                $pedido->comanda?->recalcularTotal();
                // Cancelado: o estoque volta (se o item foi descartado, a loja lança a perda/avaria)
                app(EstoqueService::class)->devolverPedido($pedido);
            }

            // A mesa acompanha os pedidos (o garçom não muda a situação à mão)
            $pedido->mesa?->sincronizarStatus($userId);
        });
    }

    /** Formas que a conta da mesa aceita: as ativas do caixa, menos as que dependem do cadastro do cliente. */
    public function formasPagamento(): Collection
    {
        return FormaPagamentoCaixa::ativas()->ordenadas()
            ->whereNotIn('tipo', FormasCaixa::TIPOS_ESPECIAIS)
            ->get(['codigo', 'descricao', 'meio_pagamento', 'tipo', 'permite_troco']);
    }

    /**
     * Fecha a conta: grava os pagamentos (uma ou mais formas), conclui os pedidos e libera a mesa.
     * Pago a mais só é aceito como troco, e o troco não pode passar do que foi pago em formas que dão troco.
     *
     * @param array  $pagamentos [['forma' => codigo, 'valor' => float], ...]
     * @param string $onde       garcom | painel
     * @param array  $cliente    ['cpf' => ..., 'nome' => ...] para a NFC-e (opcional; acima do limite a tela pede)
     */
    public function fecharConta(Comanda $conta, array $pagamentos, ?int $userId, string $onde, array $cliente = []): Comanda
    {
        // CPF/nome do cliente para a NFC-e: CPF, se vier, precisa ser válido
        $cpf = preg_replace('/\D/', '', (string) ($cliente['cpf'] ?? ''));
        $nome = mb_substr(trim((string) ($cliente['nome'] ?? '')), 0, 60);
        if ($cpf !== '' && !\App\Models\Condutor::cpfValido($cpf)) {
            throw new DomainException('CPF do cliente inválido. Confira os números ou deixe em branco.');
        }

        return DB::transaction(function () use ($conta, $pagamentos, $userId, $onde, $cpf, $nome) {
            $conta = Comanda::whereKey($conta->id)->lockForUpdate()->firstOrFail();

            if ($conta->status !== Comanda::ABERTA) {
                throw new DomainException('Esta conta já foi fechada.');
            }

            // Só fecha quando todos os pedidos da mesa ficaram prontos (ou já foram entregues)
            $emPreparo = $conta->pedidos()->where('status', ComandaPedido::PREPARO)->pluck('id');
            if ($emPreparo->isNotEmpty()) {
                throw new DomainException(($emPreparo->count() === 1 ? 'O pedido #' . $emPreparo->first() . ' ainda está' : 'Os pedidos #' . $emPreparo->implode(', #') . ' ainda estão')
                    . ' em preparo. A conta só pode ser fechada quando todos os pedidos estiverem prontos.');
            }

            $conta->recalcularTotal();
            $totalCentavos = (int) round((float) $conta->total * 100);

            if ($totalCentavos <= 0) {
                throw new DomainException('A conta não tem nada a cobrar.');
            }

            $formas = $this->formasPagamento()->keyBy('codigo');
            $linhas = [];
            $pagoCentavos = 0;
            $comTrocoCentavos = 0;

            foreach ($pagamentos as $p) {
                $forma = $formas[$p['forma'] ?? ''] ?? null;
                $valor = (int) round((float) ($p['valor'] ?? 0) * 100);

                if (!$forma) {
                    throw new DomainException('Forma de pagamento inválida ou inativa.');
                }
                if ($valor <= 0) {
                    throw new DomainException('Informe o valor de cada pagamento.');
                }

                $pagoCentavos += $valor;
                if ($forma->permite_troco) {
                    $comTrocoCentavos += $valor;
                }
                $linhas[] = [$forma, $valor];
            }

            if (!$linhas) {
                throw new DomainException('Escolha a forma de pagamento.');
            }
            if ($pagoCentavos < $totalCentavos) {
                throw new DomainException('Falta R$ ' . number_format(($totalCentavos - $pagoCentavos) / 100, 2, ',', '.') . ' para fechar a conta.');
            }

            $trocoCentavos = $pagoCentavos - $totalCentavos;
            if ($trocoCentavos > $comTrocoCentavos) {
                throw new DomainException('O valor pago passou do total, e só dinheiro dá troco. Confira os valores.');
            }

            foreach ($linhas as [$forma, $valor]) {
                $conta->pagamentos()->create([
                    'forma_pagamento' => $forma->codigo,
                    'descricao'       => $forma->descricao,
                    'meio_pagamento'  => $forma->meio_pagamento,
                    'valor'           => $valor / 100,
                ]);
            }

            $conta->forceFill([
                'status'       => Comanda::PAGA,
                'troco'        => $trocoCentavos / 100,
                'fechada_em'   => now(),
                'fechada_por'  => $userId,
                'fechada_onde' => $onde,
                // Vai para a NFC-e em Pedidos Restaurante (sem CPF acima do limite, lá vira pendência)
                'dados_nfce'   => ($cpf !== '' || $nome !== '') ? array_filter(['cpf' => $cpf, 'nome' => $nome]) : $conta->dados_nfce,
            ])->save();

            $conta->pedidos()->abertos()->get()
                ->each(fn (ComandaPedido $p) => $p->mudarStatus(ComandaPedido::CONCLUIDO, $userId));

            // Conta paga: a mesa fica livre para o próximo cliente
            $conta->mesa->mudarStatus(Mesa::DISPONIVEL, $userId);

            return $conta->fresh(['pagamentos', 'mesa']);
        });
    }
}
