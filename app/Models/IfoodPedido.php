<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pedido recebido do iFood. Os itens vão para o pedido da Lista de Pedidos (comanda_pedido); aqui ficam
 * os dados que só o iFood tem: tipo (entrega/retirada), cliente, endereço, pagamentos e a situação lá.
 */
class IfoodPedido extends Model
{
    // Situações do pedido no iFood (eventos)
    public const PLACED          = 'PLACED';           // novo, aguardando aceite
    public const CONFIRMED       = 'CONFIRMED';
    public const READY_TO_PICKUP = 'READY_TO_PICKUP';
    public const DISPATCHED      = 'DISPATCHED';
    public const CONCLUDED       = 'CONCLUDED';
    public const CANCELLED       = 'CANCELLED';

    /** iFood como intermediador na NFC-e (indIntermed 1): CNPJ da iFood.com Agência de Restaurantes Online S.A. */
    public const CNPJ_INTERMEDIADOR = '14380200000121';

    public const TIPOS = [
        'DELIVERY' => 'Entrega',
        'TAKEOUT'  => 'Retirada',
        'DINE_IN'  => 'Comer no local',
        'INDOOR'   => 'No local',
    ];

    public const METODOS = [
        'CREDIT' => 'Crédito', 'DEBIT' => 'Débito', 'MEAL_VOUCHER' => 'Vale-refeição', 'FOOD_VOUCHER' => 'Vale-alimentação',
        'CASH' => 'Dinheiro', 'PIX' => 'PIX', 'DIGITAL_WALLET' => 'Carteira digital', 'GIFT_CARD' => 'Vale-presente', 'OTHER' => 'Outro',
    ];

    protected $table = 'ifood_pedidos';

    protected $fillable = [
        'ifood_order_id', 'display_id', 'comanda_pedido_id', 'ifood_cliente_id', 'merchant_id', 'tipo', 'momento', 'status',
        'entregue_por', 'cliente_nome', 'cliente_telefone', 'localizador', 'endereco', 'codigo_retirada',
        'subtotal', 'taxa_entrega', 'descontos', 'taxas_adicionais', 'total', 'pago_online', 'cobrar_na_entrega',
        'pagamentos', 'observacoes', 'agendado_para', 'criado_ifood_em', 'confirmado_em', 'ultimo_erro', 'payload',
        'codigo_entrega_solicitado_em', 'cancelamento_solicitado_em', 'cancelado_por', 'disputa', 'dados_nfce',
    ];

    protected $casts = [
        'pagamentos'       => 'array',
        'payload'          => 'array',
        'subtotal'         => 'decimal:2',
        'taxa_entrega'     => 'decimal:2',
        'descontos'        => 'decimal:2',
        'taxas_adicionais' => 'decimal:2',
        'total'            => 'decimal:2',
        'pago_online'      => 'decimal:2',
        'cobrar_na_entrega' => 'decimal:2',
        'agendado_para'    => 'datetime',
        'criado_ifood_em'  => 'datetime',
        'confirmado_em'    => 'datetime',
        'codigo_entrega_solicitado_em' => 'datetime',
        'cancelamento_solicitado_em'   => 'datetime',
        'disputa'          => 'array',
        'dados_nfce'       => 'array', // CPF e endereço informados pelo operador para a NFC-e de entrega
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(ComandaPedido::class, 'comanda_pedido_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(IfoodCliente::class, 'ifood_cliente_id');
    }

    public function aguardandoAceite(): bool
    {
        return $this->status === self::PLACED;
    }

    /** Entrega feita pela própria loja: o iFood só conclui com o código que o cliente informa ao entregador. */
    public function entregaPropria(): bool
    {
        return $this->tipo === 'DELIVERY' && $this->entregue_por === 'MERCHANT';
    }

    /** Ações da disputa (o que o cliente pede) em português. */
    public const ACOES_DISPUTA = [
        'CANCELLATION'           => 'Cancelar o pedido',
        'PARTIAL_CANCELLATION'   => 'Cancelar parte do pedido',
        'PROPOSED_AMOUNT_REFUND' => 'Reembolso de um valor',
    ];

    public function paraTela(): array
    {
        return [
            'order_id'          => $this->ifood_order_id,
            'numero'            => $this->display_id,
            'tipo'              => $this->tipo,
            'tipo_rotulo'       => self::TIPOS[$this->tipo] ?? $this->tipo,
            'status'            => $this->status,
            'aguardando_aceite' => $this->aguardandoAceite(),
            'entregue_por'      => $this->entregue_por,
            'cliente'           => $this->cliente_nome,
            'telefone'          => $this->cliente_telefone,
            'localizador'       => $this->localizador,
            'endereco'          => $this->endereco,
            'codigo_retirada'   => $this->codigo_retirada,
            'subtotal'          => (float) $this->subtotal,
            'taxa_entrega'      => (float) $this->taxa_entrega,
            'descontos'         => (float) $this->descontos,
            'taxas_adicionais'  => (float) $this->taxas_adicionais,
            'total'             => (float) $this->total,
            'pago_online'       => (float) $this->pago_online,
            'cobrar_na_entrega' => (float) $this->cobrar_na_entrega,
            'pagamentos'        => collect($this->pagamentos ?? [])->map(fn ($p) => [
                'descricao' => (self::METODOS[$p['metodo'] ?? ''] ?? ($p['metodo'] ?? 'Pagamento'))
                    . (!empty($p['bandeira']) ? ' ' . $p['bandeira'] : '')
                    . (($p['tipo'] ?? '') === 'ONLINE' ? ' (pago no app)' : ' (na entrega)'),
                'valor'     => (float) ($p['valor'] ?? 0),
                'troco_para' => isset($p['troco_para']) ? (float) $p['troco_para'] : null,
            ])->values(),
            'observacoes'       => $this->observacoes,
            // Cliente do iFood (só quando carregado com a contagem): "Cliente novo" ou "Nº pedido"
            'cliente_cadastro'  => $this->relationLoaded('cliente') && $this->cliente ? [
                'id'           => $this->cliente->id,
                'pedidos'      => (int) ($this->cliente->pedidos_validos ?? 0),
                'pedidos_loja' => $this->cliente->pedidos_na_loja_ifood,
                'desde'        => $this->cliente->primeiro_pedido_em?->toIso8601String(),
                'observacao'   => $this->cliente->observacao,
                'url'          => route('clientes-ifood.show', $this->cliente->id),
            ] : null,
            'agendado_para'     => $this->agendado_para?->toIso8601String(),
            'erro'              => $this->ultimo_erro,
            'entrega_propria'   => $this->entregaPropria(),
            'codigo_entrega_solicitado' => $this->codigo_entrega_solicitado_em !== null,
            'cancelamento_solicitado'   => $this->cancelamento_solicitado_em !== null && $this->status !== self::CANCELLED,
            'cancelado_por'     => $this->cancelado_por,
            'disputa'           => $this->disputa ? [
                'acao'      => $this->disputa['acao'] ?? null,
                'acao_rotulo' => self::ACOES_DISPUTA[$this->disputa['acao'] ?? ''] ?? ($this->disputa['acao'] ?? 'Pedido do cliente'),
                'mensagem'  => $this->disputa['mensagem'] ?? null,
                'expira_em' => $this->disputa['expira_em'] ?? null,
                'no_prazo'  => $this->disputa['no_prazo'] ?? null,
                'itens'     => $this->disputa['itens'] ?? [],
                'valor'     => $this->disputa['valor'] ?? null,
            ] : null,
        ];
    }
}
