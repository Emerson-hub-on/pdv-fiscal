<?php

namespace App\Services;

use App\Models\ContaReceber;
use App\Models\ContaReceberHistorico;
use App\Models\IfoodConfiguracao;
use App\Models\IfoodPedido;
use App\Models\TipoContaReceber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Conciliação financeira do iFood (Financial API v3).
 *
 * - Eventos financeiros (/financial-events): venda, comissão, taxas, cancelamento, subsídio... por pedido.
 *   Repasse líquido = soma de amount.value dos eventos com hasTransferImpact = true (regra do iFood). Eventos com
 *   hasTransferImpact = false foram pagos direto à loja (ex.: dinheiro na entrega): entram como "recebido direto".
 * - Repasses (/settlements): o que o iFood pagou ou vai pagar (REPASSE) ou cobrar (BOLETO).
 *   Cada REPASSE vira uma conta a receber (origem "ifood"); quando o iFood marca SUCCEED, a conta é baixada.
 *
 * As consultas são só de leitura no iFood. Os exemplos de resposta vieram da assistente da documentação (não são o
 * schema oficial): a leitura é tolerante e o JSON original de cada evento/repasse fica gravado.
 */
class IfoodFinanceiroService
{
    public const ORIGEM_CONTA = 'ifood';

    /** Categoria de cada evento pelo nome (só para a tela; o líquido é sempre a soma dos eventos). */
    public const CATEGORIAS = [
        'venda'        => 'Vendas',
        'cancelamento' => 'Cancelamentos',
        'comissao'     => 'Comissões',
        'taxa'         => 'Taxas',
        'entrega'      => 'Entrega',
        'promocao'     => 'Promoções e subsídios',
        'outros'       => 'Outros',
    ];

    public const TIPOS_REPASSE = [
        'REPASSE'             => 'Repasse',
        'BOLETO'              => 'Boleto (saldo devedor)',
        'REGISTRO_RECEBIVEIS' => 'Retido (recebíveis)',
        'RENEGOCIADA'         => 'Renegociado',
    ];

    public const STATUS_REPASSE = [
        'SUCCEED'               => 'Pago',
        'PENDING'               => 'Pendente',
        'FAILED'                => 'Falhou',
        'TRANSFER_RENEGOTIATED' => 'Renegociado',
    ];

    /** Janela de cada consulta de eventos (o iFood recomenda períodos semanais). */
    private const JANELA_EVENTOS_DIAS = 7;

    /** Janela de cada consulta de repasses (o iFood limita o intervalo; 31 dias fica abaixo do limite). */
    private const JANELA_REPASSES_DIAS = 31;

    private const TAMANHO_PAGINA = 100;
    private const MAX_PAGINAS = 200;

    public function __construct(private IfoodService $ifood)
    {
    }

    public static function categoria(string $nome): string
    {
        $n = strtoupper($nome);

        return match (true) {
            (bool) preg_match('/CANCEL|REFUND|CHARGEBACK|REVERS|ESTORNO/', $n) => 'cancelamento',
            str_contains($n, 'COMMISSION')                                      => 'comissao',
            (bool) preg_match('/DELIVERY|FREIGHT|LOGISTIC|SHIPPING/', $n)       => 'entrega',
            str_contains($n, 'FEE')                                             => 'taxa',
            (bool) preg_match('/SUBSIDY|PROMOTION|DISCOUNT|VOUCHER|BENEFIT|INCENTIVE|CASHBACK/', $n) => 'promocao',
            (bool) preg_match('/SALE|PAYMENT_CREDIT/', $n)                      => 'venda',
            default                                                              => 'outros',
        };
    }

    /**
     * Busca no iFood os eventos financeiros e os repasses do período e grava. Os repasses viram contas a receber.
     *
     * @return array{eventos: int, repasses: int, contas: int}
     */
    public function sincronizar(?IfoodConfiguracao $cfg = null, ?Carbon $inicio = null, ?Carbon $fim = null): array
    {
        $cfg ??= IfoodConfiguracao::atual();
        if (!$cfg->ativo || !$cfg->credenciaisPreenchidas()) {
            throw new RuntimeException('Integração com o iFood desligada ou sem credenciais (Cadastros → iFood).');
        }

        $trava = Cache::lock('ifood-financeiro-sincronizar', 300);
        if (!$trava->get()) {
            throw new RuntimeException('Já existe uma sincronização financeira em andamento. Aguarde alguns minutos.');
        }

        try {
            $inicio ??= now()->subDays(35)->startOfDay();
            $fim ??= now()->startOfDay();

            $eventos = $this->sincronizarEventos($cfg, $inicio, $fim);
            // Repasses: pagos no período e os previstos para as próximas semanas
            [$repasses, $contas] = $this->sincronizarRepasses($cfg, $inicio, now()->addDays(31)->startOfDay());

            Cache::forever('ifood-financeiro-sincronizado-em', now()->toIso8601String());

            return ['eventos' => $eventos, 'repasses' => $repasses, 'contas' => $contas];
        } finally {
            $trava->release();
        }
    }

    /** Sincroniza sozinho de tempos em tempos (chamado pelo polling): no máximo a cada 6 h. */
    public function sincronizarSePreciso(): void
    {
        $ultima = Cache::get('ifood-financeiro-sincronizado-em');
        if ($ultima && Carbon::parse($ultima)->gt(now()->subHours(6))) {
            return;
        }
        if (Cache::has('ifood-financeiro-tentativa')) {
            return;
        }
        Cache::put('ifood-financeiro-tentativa', true, now()->addHour()); // erro: tenta de novo só daqui a 1 h

        try {
            $this->sincronizar();
        } catch (Throwable $e) {
            Log::warning('iFood: sincronização financeira automática falhou: ' . $e->getMessage());
        }
    }

    public static function sincronizadoEm(): ?string
    {
        return Cache::get('ifood-financeiro-sincronizado-em');
    }

    // ------------------------------------------------------------------
    // Eventos financeiros
    // ------------------------------------------------------------------

    private function sincronizarEventos(IfoodConfiguracao $cfg, Carbon $inicio, Carbon $fim): int
    {
        $total = 0;
        $m = $cfg->merchant_id;

        for ($de = $inicio->copy(); $de->lte($fim); $de->addDays(self::JANELA_EVENTOS_DIAS)) {
            $ate = $de->copy()->addDays(self::JANELA_EVENTOS_DIAS - 1)->min($fim);
            $eventos = collect();

            for ($pagina = 1; $pagina <= self::MAX_PAGINAS; $pagina++) {
                $r = $this->ifood->consultar($cfg, "/financial/v3.0/merchants/{$m}/financial-events", [
                    'beginDate' => $de->toDateString(),
                    'endDate'   => $ate->toDateString(),
                    'page'      => $pagina,
                    'size'      => self::TAMANHO_PAGINA,
                ]);

                [$lista, $temProxima] = $this->paginaEventos($r);
                $eventos = $eventos->concat($lista);

                if (!$temProxima) {
                    break;
                }
            }

            $total += $this->gravarEventos($m, $eventos);
        }

        return $total;
    }

    /** A resposta pode vir como lista ou como objeto { financialEvents, hasNextPage }. */
    private function paginaEventos(mixed $r): array
    {
        if (!is_array($r) || $r === []) {
            return [[], false];
        }

        if (array_is_list($r)) {
            return [array_values(array_filter($r, 'is_array')), count($r) >= self::TAMANHO_PAGINA];
        }

        $lista = $r['financialEvents'] ?? $r['events'] ?? $r['data'] ?? $r['content'] ?? null;
        if (!is_array($lista)) {
            throw new RuntimeException('Resposta inesperada do iFood nos eventos financeiros (campos: ' . implode(', ', array_keys($r)) . ').');
        }

        $temProxima = isset($r['hasNextPage'])
            ? (bool) $r['hasNextPage']
            : count($lista) >= self::TAMANHO_PAGINA;

        return [array_values(array_filter($lista, 'is_array')), $temProxima];
    }

    private function gravarEventos(string $merchantId, Collection $eventos): int
    {
        $ocorrencias = [];
        $agora = now();
        $linhas = [];

        foreach ($eventos as $e) {
            $valor = $this->decimal(data_get($e, 'amount.value', data_get($e, 'amount')));
            if ($valor === null) {
                Log::warning('iFood: evento financeiro sem valor ignorado', ['evento' => $e]);
                continue;
            }

            // Sem id próprio: hash do evento + ocorrência (dois eventos idênticos no mesmo pedido contam duas vezes)
            $hash = sha1(json_encode($this->ordenar($e)));
            $ocorrencias[$hash] = ($ocorrencias[$hash] ?? 0) + 1;

            $linhas[] = [
                'chave'            => $hash . '#' . $ocorrencias[$hash],
                'merchant_id'      => mb_substr((string) (data_get($e, 'receiver.merchantId') ?: $merchantId), 0, 64),
                'nome'             => mb_substr((string) ($e['name'] ?? 'DESCONHECIDO'), 0, 60),
                'descricao'        => mb_substr((string) ($e['description'] ?? ''), 0, 200) ?: null,
                'produto'          => mb_substr((string) ($e['product'] ?? ''), 0, 30) ?: null,
                'gatilho'          => mb_substr((string) ($e['trigger'] ?? ''), 0, 60) ?: null,
                'ocorrido_em'      => $this->dataHora($e['dateTime'] ?? null),
                'competencia'      => mb_substr((string) ($e['competence'] ?? ''), 0, 7) ?: null,
                'periodo_inicio'   => $this->data(data_get($e, 'period.beginDate')),
                'periodo_fim'      => $this->data(data_get($e, 'period.endDate')),
                'referencia_tipo'  => mb_substr((string) data_get($e, 'reference.type', ''), 0, 30) ?: null,
                'referencia_id'    => mb_substr((string) data_get($e, 'reference.id', ''), 0, 64) ?: null,
                'referencia_em'    => $this->dataHora(data_get($e, 'reference.date')),
                'impacta_repasse'  => filter_var($e['hasTransferImpact'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'valor'            => $valor,
                'base_calculo'     => $this->decimal(data_get($e, 'billing.baseValue')),
                'percentual'       => $this->decimal(data_get($e, 'billing.feePercentage'), 4),
                'previsao_repasse' => $this->data(data_get($e, 'settlement.expectedDate')),
                'pagamento_metodo' => mb_substr((string) data_get($e, 'payment.method', ''), 0, 30) ?: null,
                'pagamento_bandeira' => mb_substr((string) data_get($e, 'payment.brand', ''), 0, 40) ?: null,
                'responsavel'      => mb_substr((string) data_get($e, 'payment.liability', ''), 0, 30) ?: null,
                // Documento do recebedor não é guardado
                'payload'          => json_encode($this->semDocumento($e), JSON_UNESCAPED_UNICODE),
                'created_at'       => $agora,
                'updated_at'       => $agora,
            ];
        }

        foreach (array_chunk($linhas, 200) as $lote) {
            DB::table('ifood_eventos_financeiros')->upsert($lote, ['chave'], array_diff(array_keys($lote[0]), ['chave', 'created_at']));
        }

        return count($linhas);
    }

    // ------------------------------------------------------------------
    // Repasses
    // ------------------------------------------------------------------

    /** @return array{0: int, 1: int} [repasses gravados, contas a receber criadas/atualizadas] */
    private function sincronizarRepasses(IfoodConfiguracao $cfg, Carbon $inicio, Carbon $fim): array
    {
        $m = $cfg->merchant_id;
        $itens = collect();

        for ($de = $inicio->copy(); $de->lte($fim); $de->addDays(self::JANELA_REPASSES_DIAS)) {
            $ate = $de->copy()->addDays(self::JANELA_REPASSES_DIAS - 1)->min($fim);
            $r = $this->ifood->consultar($cfg, "/financial/v3.0/merchants/{$m}/settlements", [
                'beginPaymentDate' => $de->toDateString(),
                'endPaymentDate'   => $ate->toDateString(),
            ]);

            foreach ((array) ($r['settlements'] ?? []) as $s) {
                foreach ((array) ($s['closingItems'] ?? []) as $item) {
                    if (is_array($item) && !empty($item['id'])) {
                        $itens->push($item + [
                            '_apuracao_inicio' => $s['startDateCalculation'] ?? null,
                            '_apuracao_fim'    => $s['endDateCalculation'] ?? null,
                        ]);
                    }
                }
            }
        }

        $contas = 0;
        foreach ($itens->unique('id') as $item) {
            $valor = $this->decimal($item['amount'] ?? null) ?? 0.0;
            $semConta = $item;
            unset($semConta['accountDetails']); // agência e conta não são gravadas

            $repasse = DB::transaction(function () use ($item, $valor, $semConta, $m) {
                DB::table('ifood_repasses')->upsert([[
                    'item_id'         => mb_substr((string) $item['id'], 0, 64),
                    'merchant_id'     => $m,
                    'tipo'            => mb_substr(strtoupper((string) ($item['type'] ?? 'REPASSE')), 0, 30),
                    'produto'         => mb_substr((string) ($item['product'] ?? ''), 0, 30) ?: null,
                    'status'          => mb_substr(strtoupper((string) ($item['status'] ?? '')), 0, 40) ?: null,
                    'valor'           => $valor,
                    'data_pagamento'  => $this->data($item['paymentDate'] ?? null),
                    'apuracao_inicio' => $this->data($item['_apuracao_inicio']),
                    'apuracao_fim'    => $this->data($item['_apuracao_fim']),
                    'transacao_id'    => mb_substr((string) ($item['transactionId'] ?? ''), 0, 64) ?: null,
                    'banco'           => mb_substr((string) data_get($item, 'accountDetails.bankName', ''), 0, 80) ?: null,
                    'payload'         => json_encode($semConta, JSON_UNESCAPED_UNICODE),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]], ['item_id'], ['tipo', 'produto', 'status', 'valor', 'data_pagamento', 'apuracao_inicio', 'apuracao_fim', 'transacao_id', 'banco', 'payload', 'updated_at']);

                return DB::table('ifood_repasses')->where('item_id', (string) $item['id'])->lockForUpdate()->first();
            });

            if ($this->contaDoRepasse($repasse)) {
                $contas++;
            }
        }

        return [$itens->unique('id')->count(), $contas];
    }

    /**
     * Repasse positivo vira conta a receber (uma por repasse). Pago (SUCCEED) = parcela baixada na data do pagamento.
     * Conta já recebida não é mexida. Boleto/renegociado/retido não geram conta (aparecem só na tela).
     */
    private function contaDoRepasse(object $repasse): bool
    {
        if ($repasse->tipo !== 'REPASSE' || (float) $repasse->valor <= 0 || !$repasse->data_pagamento) {
            return false;
        }

        return DB::transaction(function () use ($repasse) {
            $conta = $repasse->conta_receber_id ? ContaReceber::with('parcelas')->find($repasse->conta_receber_id) : null;
            $valor = round((float) $repasse->valor, 2);
            $periodo = $repasse->apuracao_inicio && $repasse->apuracao_fim
                ? Carbon::parse($repasse->apuracao_inicio)->format('d/m') . ' a ' . Carbon::parse($repasse->apuracao_fim)->format('d/m/Y')
                : Carbon::parse($repasse->data_pagamento)->format('d/m/Y');

            if (!$conta) {
                $conta = ContaReceber::create([
                    'tipo_conta_receber_id' => TipoContaReceber::firstOrCreate(['descricao' => 'Repasse iFood'], ['ativo' => true])->id,
                    'origem'           => self::ORIGEM_CONTA,
                    'descricao'        => mb_substr("Repasse iFood — vendas de {$periodo}", 0, 150),
                    'numero_documento' => mb_substr('IFOOD-' . $repasse->item_id, 0, 30),
                    'data_conta'       => $repasse->apuracao_fim ?: $repasse->data_pagamento,
                    'valor_total'      => $valor,
                    'observacao'       => 'Lançada pela conciliação do iFood (repasse ' . $repasse->item_id . ').',
                    'status'           => 'aberta',
                ]);
                $conta->parcelas()->create(['numero' => 1, 'vencimento' => $repasse->data_pagamento, 'valor' => $valor]);
                ContaReceberHistorico::registrar($conta, 'criacao', [
                    'origem' => 'sistema', 'valor' => $valor, 'user_id' => null,
                    'descricao' => "Repasse do iFood ({$periodo}), previsto para " . Carbon::parse($repasse->data_pagamento)->format('d/m/Y'),
                ]);
                DB::table('ifood_repasses')->where('id', $repasse->id)->update(['conta_receber_id' => $conta->id]);
                $conta->load('parcelas');
            }

            if ($conta->status === 'recebida') {
                return true;
            }

            // Ainda em aberto: acompanha valor e data que o iFood informar
            $parcela = $conta->parcelas->first();
            if ($parcela && !$parcela->data_recebimento && (abs((float) $parcela->valor - $valor) > 0.001 || $parcela->vencimento?->toDateString() !== $repasse->data_pagamento)) {
                $parcela->update(['valor' => $valor, 'vencimento' => $repasse->data_pagamento]);
                $conta->update(['valor_total' => $valor]);
            }

            if ($repasse->status === 'SUCCEED' && $parcela && !$parcela->data_recebimento) {
                $parcela->update(['data_recebimento' => $repasse->data_pagamento, 'valor_recebido' => $valor]);
                ContaReceberHistorico::registrar($conta, 'baixa', [
                    'origem' => 'sistema', 'valor' => $valor, 'user_id' => null, 'forma' => 'Repasse iFood',
                    'descricao' => 'Baixa automática: o iFood informou o repasse como pago' . ($repasse->banco ? " ({$repasse->banco})" : '') . '.',
                    'ocorrido_em' => Carbon::parse($repasse->data_pagamento),
                ], $parcela);
                $conta->recalcularStatus();
            }

            return true;
        });
    }

    // ------------------------------------------------------------------
    // Conciliação (tela)
    // ------------------------------------------------------------------

    /**
     * Uma linha por pedido do iFood no período (pela data do pedido): o que o iFood lançou (por categoria),
     * o líquido do repasse e a comparação com o pedido no sistema.
     */
    public function conciliacao(string $inicio, string $fim): Collection
    {
        $eventos = DB::table('ifood_eventos_financeiros')
            ->where('referencia_tipo', 'ORDER')
            ->whereNotNull('referencia_id')
            ->whereRaw('DATE(COALESCE(referencia_em, ocorrido_em)) BETWEEN ? AND ?', [$inicio, $fim])
            ->orderBy('ocorrido_em')
            ->get()
            ->groupBy('referencia_id');

        // Pedidos do sistema no período (para achar os que o iFood ainda não lançou)
        $pedidos = IfoodPedido::with('pedido')
            ->where(fn ($q) => $q->whereIn('ifood_order_id', $eventos->keys()->all())
                ->orWhereBetween(DB::raw('DATE(COALESCE(criado_ifood_em, created_at))'), [$inicio, $fim]))
            ->get()
            ->keyBy('ifood_order_id');

        $ids = $eventos->keys()->merge($pedidos->keys())->unique()->values();

        return $ids->map(function (string $orderId) use ($eventos, $pedidos) {
            $lista = $eventos->get($orderId, collect());
            $pedido = $pedidos->get($orderId);
            $porCategoria = array_fill_keys(array_keys(self::CATEGORIAS), 0.0);

            foreach ($lista as $e) {
                $porCategoria[self::categoria($e->nome)] += (float) $e->valor;
            }

            $liquido = round((float) $lista->where('impacta_repasse', 1)->sum('valor'), 2);
            $direto = round((float) $lista->where('impacta_repasse', 0)->sum('valor'), 2);
            $vendaIfood = round($porCategoria['venda'], 2);
            $sistema = $pedido ? round((float) $pedido->total, 2) : null;
            $cancelado = $pedido && ($pedido->status === IfoodPedido::CANCELLED || $pedido->pedido?->status === 'cancelado');
            $dataPedido = $pedido?->criado_ifood_em ?? $pedido?->created_at
                ?? ($lista->first()?->referencia_em ? Carbon::parse($lista->first()->referencia_em) : null);

            [$situacao, $motivo] = match (true) {
                !$pedido => ['sem_sistema', 'O iFood lançou valores de um pedido que não está no sistema.'],
                $lista->isEmpty() && $cancelado => ['ok', 'Cancelado, sem lançamento no iFood.'],
                $lista->isEmpty() && $dataPedido && $dataPedido->lt(now()->subDays(2)) => ['sem_evento', 'Pedido do sistema sem lançamento financeiro do iFood há mais de 2 dias.'],
                $lista->isEmpty() => ['aguardando', 'O iFood ainda não lançou este pedido.'],
                $cancelado && round($vendaIfood + $porCategoria['cancelamento'], 2) > 0.009 => ['divergente', 'Cancelado no sistema, mas o iFood lançou a venda sem o cancelamento.'],
                // A venda do iFood pode vir pelo total ou pelo subtotal (sem a entrega): confere com os dois
                !$cancelado && $vendaIfood > 0 && abs($vendaIfood - $sistema) > 0.05
                    && abs($vendaIfood - round((float) $pedido->subtotal, 2)) > 0.05 => ['divergente', 'Valor da venda no iFood diferente do pedido no sistema.'],
                default => ['ok', null],
            };

            $previsao = $lista->pluck('previsao_repasse')->filter()->max();

            return [
                'order_id'     => $orderId,
                'pedido'       => $pedido ? '#' . ($pedido->display_id ?: $pedido->id) : '—',
                'tipo'         => $pedido ? (IfoodPedido::TIPOS[$pedido->tipo] ?? $pedido->tipo) : '',
                'data'         => $dataPedido?->toIso8601String(),
                'cliente'      => (string) ($pedido?->cliente_nome ?? ''),
                'sistema'      => $sistema,
                'cancelado'    => $cancelado,
                'categorias'   => array_map(fn ($v) => round($v, 2), $porCategoria),
                'liquido'      => $liquido,
                'direto'       => $direto,
                'previsao'     => $previsao,
                'situacao'     => $situacao,
                'motivo'       => $motivo,
                'eventos'      => $lista->map(fn ($e) => [
                    'nome'       => $e->nome,
                    'descricao'  => $e->descricao ?: $e->nome,
                    'categoria'  => self::CATEGORIAS[self::categoria($e->nome)],
                    'valor'      => (float) $e->valor,
                    'impacta'    => (bool) $e->impacta_repasse,
                    'base'       => $e->base_calculo !== null ? (float) $e->base_calculo : null,
                    'percentual' => $e->percentual !== null ? (float) $e->percentual : null,
                    'quando'     => $e->ocorrido_em,
                    'previsao'   => $e->previsao_repasse,
                    'pagamento'  => trim(($e->pagamento_metodo ?? '') . ' ' . ($e->pagamento_bandeira ?? '')),
                ])->values()->all(),
            ];
        })->sortByDesc('data')->values();
    }

    /** Eventos sem pedido (ajustes, mensalidade, retenções...) no período. */
    public function eventosSemPedido(string $inicio, string $fim): Collection
    {
        return DB::table('ifood_eventos_financeiros')
            ->where(fn ($q) => $q->whereNull('referencia_tipo')->orWhere('referencia_tipo', '!=', 'ORDER')->orWhereNull('referencia_id'))
            ->whereRaw('DATE(ocorrido_em) BETWEEN ? AND ?', [$inicio, $fim])
            ->orderByDesc('ocorrido_em')
            ->get();
    }

    public function repasses(string $inicio, string $fim): Collection
    {
        return DB::table('ifood_repasses')
            ->leftJoin('contas_receber', 'contas_receber.id', '=', 'ifood_repasses.conta_receber_id')
            ->whereBetween('ifood_repasses.data_pagamento', [$inicio, $fim])
            ->orderByDesc('ifood_repasses.data_pagamento')
            ->get(['ifood_repasses.*', 'contas_receber.status as conta_status']);
    }

    // ------------------------------------------------------------------
    // Auxiliares
    // ------------------------------------------------------------------

    private function decimal(mixed $v, int $casas = 2): ?float
    {
        if (is_array($v)) {
            $v = $v['value'] ?? null;
        }
        if ($v === null || $v === '' || !is_numeric(str_replace(',', '.', (string) $v))) {
            return null;
        }

        return round((float) str_replace(',', '.', (string) $v), $casas);
    }

    private function data(mixed $v): ?string
    {
        if (!is_string($v) || $v === '') {
            return null;
        }

        try {
            return Carbon::parse($v)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function dataHora(mixed $v): ?string
    {
        if (!is_string($v) || $v === '') {
            return null;
        }

        try {
            return Carbon::parse($v)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    private function ordenar(array $a): array
    {
        ksort($a);
        foreach ($a as $k => $v) {
            if (is_array($v)) {
                $a[$k] = $this->ordenar($v);
            }
        }

        return $a;
    }

    private function semDocumento(array $e): array
    {
        unset($e['receiver']['merchantDocument']);

        return $e;
    }
}
