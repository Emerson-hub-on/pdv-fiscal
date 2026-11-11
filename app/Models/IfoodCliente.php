<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Cliente do iFood (pelo customer.id do iFood). O cadastro é atualizado a cada pedido que chega;
 * pedidos, gasto e datas saem dos pedidos (ifood_pedidos.ifood_cliente_id).
 */
class IfoodCliente extends Model
{
    protected $table = 'ifood_clientes';

    protected $fillable = [
        'ifood_customer_id', 'nome', 'cpf', 'segmentacao', 'pedidos_na_loja_ifood', 'ultimo_endereco',
        'bairro', 'cidade', 'uf', 'primeiro_pedido_em', 'ultimo_pedido_em', 'observacao',
    ];

    protected $casts = [
        'primeiro_pedido_em'    => 'datetime',
        'ultimo_pedido_em'      => 'datetime',
        'pedidos_na_loja_ifood' => 'integer',
    ];

    /** Segmentos que o iFood manda (o que vier diferente aparece como veio). */
    public const SEGMENTOS = [
        'NEW_CUSTOMER' => 'Novo', 'NEW' => 'Novo', 'Novo' => 'Novo',
        'RECURRENT'    => 'Recorrente', 'Recorrente' => 'Recorrente',
        'VIP'          => 'VIP',
        'Cliente'      => 'Cliente',
    ];

    public function pedidos(): HasMany
    {
        return $this->hasMany(IfoodPedido::class, 'ifood_cliente_id');
    }

    public function segmento(): string
    {
        return self::SEGMENTOS[$this->segmentacao] ?? ($this->segmentacao ?: '—');
    }

    public function cpfFormatado(): string
    {
        return $this->cpf ? preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $this->cpf) : '';
    }

    /**
     * Cadastra/atualiza o cliente com os dados do pedido e liga o pedido a ele.
     * Pedido sem customer.id (raro) fica sem cliente.
     */
    public static function registrar(IfoodPedido $pedido): ?self
    {
        $p = (array) ($pedido->payload ?? []);
        $id = trim((string) data_get($p, 'customer.id'));
        if ($id === '') {
            return null;
        }

        $quando = $pedido->criado_ifood_em ?? $pedido->created_at ?? now();
        $end = (array) data_get($p, 'delivery.deliveryAddress', []);
        $cpf = preg_replace('/\D/', '', (string) (data_get($pedido->dados_nfce, 'cpf') ?: data_get($p, 'customer.documentNumber')));

        $cliente = self::firstOrNew(['ifood_customer_id' => mb_substr($id, 0, 64)]);
        $maisRecente = !$cliente->ultimo_pedido_em || Carbon::parse($quando)->gte($cliente->ultimo_pedido_em);

        // Os dados do pedido mais novo valem (nome, endereço, segmento); o CPF fica quando aparece
        if ($maisRecente) {
            $cliente->fill(array_filter([
                'nome'                  => mb_substr(trim((string) data_get($p, 'customer.name')), 0, 120) ?: null,
                'segmentacao'           => mb_substr((string) data_get($p, 'customer.segmentation', ''), 0, 40) ?: null,
                'pedidos_na_loja_ifood' => is_numeric(data_get($p, 'customer.ordersCountOnMerchant')) ? (int) data_get($p, 'customer.ordersCountOnMerchant') : null,
                'ultimo_endereco'       => $pedido->endereco,
                'bairro'                => mb_substr(trim((string) ($end['neighborhood'] ?? '')), 0, 80) ?: null,
                'cidade'                => mb_substr(trim((string) ($end['city'] ?? '')), 0, 80) ?: null,
                'uf'                    => mb_substr(mb_strtoupper(trim((string) ($end['state'] ?? ''))), 0, 2) ?: null,
            ], fn ($v) => $v !== null));
            $cliente->ultimo_pedido_em = $quando;
        }
        if (strlen($cpf) === 11) {
            $cliente->cpf = $cpf;
        }
        if (!$cliente->primeiro_pedido_em || Carbon::parse($quando)->lt($cliente->primeiro_pedido_em)) {
            $cliente->primeiro_pedido_em = $quando;
        }
        $cliente->save();

        if ($pedido->ifood_cliente_id !== $cliente->id) {
            $pedido->forceFill(['ifood_cliente_id' => $cliente->id])->saveQuietly();
        }

        return $cliente;
    }
}
