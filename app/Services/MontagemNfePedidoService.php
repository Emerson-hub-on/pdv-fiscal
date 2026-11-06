<?php

namespace App\Services;

use App\Models\FormaPagamento;
use App\Models\NotaFiscal;
use App\Models\NotaFiscalItem;
use App\Models\PedidoVenda;
use App\Models\Venda;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Faturamento → Montagem NF-e → A partir de pedido.
 * Mesmo fluxo da montagem por NFC-e: localiza o pedido de venda e monta, em memória, uma NF-e
 * com itens, cliente e forma de pagamento. O operador confere e salva como rascunho.
 *
 * Para não faturar o mesmo pedido duas vezes:
 *  - só pedido "finalizado" (em aberto) pode gerar NF-e;
 *  - pedido vendido no caixa (NFC-e) fica "emitido": a NF-e sai pela montagem a partir de NFC-e;
 *  - ao emitir a NF-e o pedido passa a "emitido" (sai da lista do caixa); cancelada a NF-e, volta a "finalizado".
 */
class MontagemNfePedidoService extends MontagemNfeService
{
    /** Motivo que impede montar NF-e deste pedido (null = pode montar). */
    public static function erroPedidoParaMontagem(?PedidoVenda $pedido): ?string
    {
        if (!$pedido) {
            return 'Pedido não encontrado.';
        }

        $numero = $pedido->numeroExibicao();

        if ($pedido->status === 'cancelado') {
            return "O pedido nº {$numero} está cancelado: não é possível gerar NF-e a partir dele.";
        }

        $nfe = self::nfeDoPedido($pedido);
        if ($nfe) {
            $rotulo = $nfe->numero ? "nº {$nfe->numero}" : "(rascunho #{$nfe->id})";

            return "Já existe uma NF-e {$rotulo} gerada a partir deste pedido (situação: {$nfe->status}).";
        }

        $venda = self::vendaDoPedido($pedido);
        if ($venda) {
            return "O pedido nº {$numero} já foi vendido no caixa (NFC-e nº {$venda->numero_nfce}, série {$venda->serie_nfce}). "
                . 'Para gerar a NF-e dessa venda, use Montagem NF-e → A partir de NFC-e.';
        }

        if ($pedido->status !== 'finalizado') {
            return "O pedido nº {$numero} não está em aberto (situação: {$pedido->status}).";
        }

        if (self::pedidoEmUsoNoCaixa($pedido)) {
            return "Venda não sincronizada: o pedido nº {$numero} já foi usado numa venda do caixa que ainda não chegou ao servidor. "
                . 'Use "Atualizar Caixa" e confira se ele foi vendido antes de gerar a NF-e.';
        }

        return null;
    }

    /** NF-e (não cancelada) já gerada a partir do pedido. */
    public static function nfeDoPedido(PedidoVenda $pedido): ?NotaFiscal
    {
        return NotaFiscal::where('pedido_venda_id', $pedido->id)
            ->where('status', '!=', 'cancelada')
            ->latest('id')
            ->first();
    }

    /** Venda do caixa (NFC-e não cancelada) feita a partir do pedido. */
    public static function vendaDoPedido(PedidoVenda $pedido): ?Venda
    {
        return Venda::where('pedido_venda_id', $pedido->id)
            ->where('status', '!=', 'cancelada')
            ->latest('id')
            ->first();
    }

    /** Pedido usado numa venda que ainda está só no caixa deste computador. */
    public static function pedidoEmUsoNoCaixa(PedidoVenda $pedido): bool
    {
        try {
            if (!is_file(config('database.connections.sqlite_local.database'))) {
                return false;
            }

            $db = DB::connection('sqlite_local');

            if (!$db->getSchemaBuilder()->hasColumn('vendas_pendentes', 'pedido_venda_id')) {
                return false;
            }

            return $db->table('vendas_pendentes')
                ->where('pedido_venda_id', $pedido->id)
                ->where('status', '!=', 'sincronizada')
                ->where(fn ($q) => $q->whereNull('status_fiscal')->orWhere('status_fiscal', '!=', 'cancelada'))
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Busca pelo número do pedido. */
    public function buscarPorNumero(int $numero): ?PedidoVenda
    {
        return PedidoVenda::with('cliente')->where('numero', $numero)->first();
    }

    /** Pedidos da data (data do pedido). */
    public function buscarPorData(string $data): Collection
    {
        return PedidoVenda::with('cliente')
            ->whereDate('data_pedido', $data)
            ->orderByDesc('numero')
            ->get();
    }

    /**
     * Monta a NF-e em memória (não salva).
     * Retorna [NotaFiscal $nota, string[] $avisos].
     */
    public function montarDoPedido(PedidoVenda $pedido): array
    {
        $pedido->loadMissing('itens.produto', 'cliente', 'formaPagamento');
        $avisos = [];

        $cliente = $pedido->cliente;
        if (!$cliente) {
            $avisos[] = 'O pedido não tem cliente: selecione o cliente da NF-e.';
        } elseif (!$cliente->ativo) {
            $avisos[] = "O cliente {$cliente->nome} está inativo no cadastro: confira antes de emitir.";
        }

        $cfop = $this->cfopPadrao($cliente, $avisos, 'fiscal.montagem_pedido_cfops');

        $forma = $pedido->formaPagamento;
        if (!$forma) {
            $avisos[] = 'O pedido não tem forma de pagamento: selecione o tipo de pagamento.';
        } elseif (!$forma->ativo || !$forma->uso_saida) {
            $avisos[] = "A forma de pagamento do pedido ({$forma->descricao}) não está ativa para venda: selecione outra.";
            $forma = null;
        }

        $nota = new NotaFiscal([
            'cliente_id'                 => $cliente?->id,
            'natureza_operacao'          => $cfop?->natureza_operacao_padrao,
            'finalidade'                 => $cfop ? ($cfop->finalidade_padrao ?? 1) : null,
            'cfop_saida_id'              => $cfop?->id,
            'forma_pagamento_id'         => $forma?->id,
            'informacoes_complementares' => "Referente ao pedido de venda nº {$pedido->numeroExibicao()}.",
            'notas_referenciadas'        => [],
            'origem_tipo'                => 'pedido',
            'pedido_venda_id'            => $pedido->id,
            'frete_por_item'             => false,
            'mod_frete'                  => 9,
        ]);

        $nota->setRelation('cliente', $cliente);
        $nota->setRelation('cfopSaida', $cfop);
        $nota->setRelation('formaPagamento', $forma);
        $nota->setRelation('pedidoVenda', $pedido);
        $nota->setRelation('venda', null);
        $nota->setRelation('transportador', null);
        $nota->setRelation('veiculo', null);
        $nota->setRelation('itens', $this->itensDoPedido($pedido, $avisos));

        if ($nota->itens->isEmpty()) {
            $avisos[] = 'Nenhum item do pedido pôde ser carregado: adicione os itens manualmente.';
        }

        return [$nota, $avisos];
    }

    private function itensDoPedido(PedidoVenda $pedido, array &$avisos): Collection
    {
        return $pedido->itens
            ->filter(function ($i) use (&$avisos) {
                if (!$i->produto) {
                    $avisos[] = "O item \"{$i->descricao}\" não existe mais no cadastro de produtos e ficou de fora.";
                    return false;
                }
                return true;
            })
            ->map(function ($i) {
                $produto = $i->produto;
                $quantidade = (float) $i->quantidade;
                $valorUnitario = (float) $i->valor_unitario;

                // Descrição do pedido (ex.: com a variante) só quando difere do nome do produto
                $descricao = trim((string) $i->descricao);
                $descricao = ($descricao !== '' && $descricao !== $produto->nome) ? $descricao : null;

                $item = new NotaFiscalItem([
                    'produto_id'            => $produto->id,
                    'produto_variante_id'   => $i->produto_variante_id,
                    'descricao'             => $descricao,
                    'ncm_id'                => $produto->ncm_id,
                    'cest_id'               => $produto->cest_id,
                    'class_trib_ibs_cbs_id' => $produto->class_trib_ibs_cbs_id,
                    'tributacao_id'         => $produto->tributacao_id,
                    'pis_cofins_id'         => $produto->pis_cofins_id,
                    'ipi_id'                => $produto->ipi_id,
                    'quantidade'            => $quantidade,
                    'valor_unitario'        => $valorUnitario,
                    'valor_desconto'        => 0,
                    'valor_outras_despesas' => 0,
                    'valor_frete'           => 0,
                    'valor_total'           => round($quantidade * $valorUnitario, 2),
                    'impostos_manuais'      => false,
                ]);
                $item->setRelation('produto', $produto);

                return $item;
            })
            ->values();
    }
}
