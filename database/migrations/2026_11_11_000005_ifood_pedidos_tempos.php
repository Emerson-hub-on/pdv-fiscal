<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indicadores do iFood: o horário de cada etapa do pedido (pelo horário do evento no iFood).
 *  - pronto_em: pronto para retirar (READY_TO_PICKUP); despachado_em: saiu para entrega (DISPATCHED);
 *  - concluido_em / cancelado_em; motivo_cancelamento: o que a loja escreveu ou o que o iFood informou.
 * Os pedidos que já existem são preenchidos pelos eventos guardados e, na falta deles, pelo pedido da cozinha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ifood_pedidos', function (Blueprint $table) {
            foreach (['pronto_em', 'despachado_em', 'concluido_em', 'cancelado_em'] as $coluna) {
                if (!Schema::hasColumn('ifood_pedidos', $coluna)) {
                    $table->timestamp($coluna)->nullable();
                }
            }
            if (!Schema::hasColumn('ifood_pedidos', 'motivo_cancelamento')) {
                $table->string('motivo_cancelamento', 255)->nullable();
            }
        });

        $etapas = [
            'pronto_em'     => ['READY_TO_PICKUP', 'RTP'],
            'despachado_em' => ['DISPATCHED', 'DSP'],
            'concluido_em'  => ['CONCLUDED', 'CON'],
            'cancelado_em'  => ['CANCELLED', 'CAN'],
        ];

        foreach (DB::table('ifood_pedidos')->select('id', 'ifood_order_id', 'comanda_pedido_id', 'status', 'ultimo_erro')->orderBy('id')->cursor() as $p) {
            $eventos = DB::table('ifood_eventos')->where('order_id', $p->ifood_order_id)->whereNotNull('criado_ifood_em')
                ->get(['codigo', 'criado_ifood_em']);
            $cozinha = $p->comanda_pedido_id
                ? DB::table('comanda_pedidos')->where('id', $p->comanda_pedido_id)->first(['pronto_em', 'concluido_em', 'cancelado_em'])
                : null;

            $dados = [];
            foreach ($etapas as $coluna => $codigos) {
                $quando = $eventos->whereIn('codigo', $codigos)->min('criado_ifood_em');
                if (!$quando && $cozinha) {
                    $quando = match ($coluna) {
                        'pronto_em'    => $cozinha->pronto_em,
                        'concluido_em' => $p->status === 'CONCLUDED' ? $cozinha->concluido_em : null,
                        'cancelado_em' => $p->status === 'CANCELLED' ? $cozinha->cancelado_em : null,
                        default        => null,
                    };
                }
                if ($quando) {
                    $dados[$coluna] = substr((string) $quando, 0, 19);
                }
            }
            if ($p->status === 'CANCELLED' && $p->ultimo_erro && str_starts_with($p->ultimo_erro, 'Cancelado pelo iFood/cliente: ')) {
                $dados['motivo_cancelamento'] = mb_substr(substr($p->ultimo_erro, strlen('Cancelado pelo iFood/cliente: ')), 0, 255);
            }
            if ($dados) {
                DB::table('ifood_pedidos')->where('id', $p->id)->update($dados);
            }
        }
    }

    public function down(): void
    {
        Schema::table('ifood_pedidos', function (Blueprint $table) {
            $table->dropColumn(['pronto_em', 'despachado_em', 'concluido_em', 'cancelado_em', 'motivo_cancelamento']);
        });
    }
};
