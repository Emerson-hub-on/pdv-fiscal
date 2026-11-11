<?php

namespace App\Services;

use App\Models\IfoodPedido;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores dos pedidos do iFood num período (pela data do pedido no iFood):
 * volume, faturamento, ticket médio, cancelamentos, tempos de cada etapa, horários de pico e os mais vendidos.
 * Tudo sai das tabelas locais (o iFood não tem API pública de indicadores de desempenho).
 */
class IfoodIndicadoresService
{
    public const TIPOS = ['todos' => 'Todos', 'DELIVERY' => 'Entrega', 'TAKEOUT' => 'Retirada'];

    public const DIAS_SEMANA = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 0 => 'Dom'];

    /** Tempo acima disso é tratado como erro de registro (ex.: pedido concluído pelo iFood horas depois) e fica fora da média. */
    private const TEMPO_MAXIMO_MIN = 6 * 60;

    /** @return array<string, mixed> */
    public function calcular(string $inicio, string $fim, string $tipo = 'todos'): array
    {
        $pedidos = $this->pedidos($inicio, $fim, $tipo);

        // Período anterior do mesmo tamanho, para comparar
        $dias = Carbon::parse($inicio)->diffInDays(Carbon::parse($fim)) + 1;
        $antInicio = Carbon::parse($inicio)->subDays($dias)->toDateString();
        $antFim = Carbon::parse($inicio)->subDay()->toDateString();
        $anterior = $this->pedidos($antInicio, $antFim, $tipo);

        $resumo = $this->resumo($pedidos);
        $resumoAnterior = $this->resumo($anterior);
        $variacao = fn (?float $atual, ?float $antes) => $atual === null || !$antes ? null : round(($atual - $antes) / $antes * 100, 1);

        return [
            'resumo'    => $resumo,
            'anterior'  => ['inicio' => $antInicio, 'fim' => $antFim] + $resumoAnterior,
            'variacao'  => [
                'pedidos'      => $variacao($resumo['pedidos'], $resumoAnterior['pedidos']),
                'faturamento'  => $variacao($resumo['faturamento'], $resumoAnterior['faturamento']),
                'ticket'       => $variacao($resumo['ticket'], $resumoAnterior['ticket']),
                'cancelamento' => $resumo['taxa_cancelamento'] === null || $resumoAnterior['taxa_cancelamento'] === null
                    ? null : round($resumo['taxa_cancelamento'] - $resumoAnterior['taxa_cancelamento'], 1), // em pontos
            ],
            'tempos'    => $this->tempos($pedidos, $anterior),
            'por_dia'   => $this->porDia($pedidos, $inicio, $fim),
            'por_hora'  => $this->porHora($pedidos),
            'mapa'      => $this->mapaCalor($pedidos),
            'por_tipo'  => $this->porTipo($pedidos),
            'cancelamentos' => $this->cancelamentos($pedidos),
            'produtos'  => $this->maisVendidos($pedidos),
        ];
    }

    /** Pedidos do período (data do pedido no iFood; sem ela, quando chegou aqui). */
    private function pedidos(string $inicio, string $fim, string $tipo): Collection
    {
        return IfoodPedido::query()
            ->where(function ($q) use ($inicio, $fim) {
                $q->whereBetween('criado_ifood_em', [$inicio . ' 00:00:00', $fim . ' 23:59:59'])
                    ->orWhere(fn ($q) => $q->whereNull('criado_ifood_em')->whereBetween('created_at', [$inicio . ' 00:00:00', $fim . ' 23:59:59']));
            })
            ->when($tipo !== 'todos', fn ($q) => $tipo === 'TAKEOUT' ? $q->where('tipo', '!=', 'DELIVERY') : $q->where('tipo', $tipo))
            ->get([
                'id', 'comanda_pedido_id', 'tipo', 'momento', 'status', 'total', 'cancelado_por', 'motivo_cancelamento',
                'criado_ifood_em', 'created_at', 'confirmado_em', 'pronto_em', 'despachado_em', 'concluido_em', 'cancelado_em',
            ])
            ->each(fn (IfoodPedido $p) => $p->setAttribute('quando', $p->criado_ifood_em ?? $p->created_at));
    }

    private function cancelado(IfoodPedido $p): bool
    {
        return $p->status === IfoodPedido::CANCELLED;
    }

    /** @return array<string, mixed> */
    private function resumo(Collection $pedidos): array
    {
        $validos = $pedidos->reject(fn ($p) => $this->cancelado($p));
        $cancelados = $pedidos->filter(fn ($p) => $this->cancelado($p));
        $faturamento = round((float) $validos->sum('total'), 2);

        return [
            'recebidos'         => $pedidos->count(),
            'pedidos'           => $validos->count(),
            'concluidos'        => $validos->where('status', IfoodPedido::CONCLUDED)->count(),
            'cancelados'        => $cancelados->count(),
            'cancelados_loja'   => $cancelados->where('cancelado_por', 'loja')->count(),
            'valor_cancelado'   => round((float) $cancelados->sum('total'), 2),
            'faturamento'       => $faturamento,
            'ticket'            => $validos->count() ? round($faturamento / $validos->count(), 2) : null,
            'taxa_cancelamento' => $pedidos->count() ? round($cancelados->count() / $pedidos->count() * 100, 1) : null,
        ];
    }

    /** Minutos entre duas marcas (null se faltar uma delas ou se for um valor fora do normal). */
    private function minutos(?Carbon $de, ?Carbon $ate): ?float
    {
        if (!$de || !$ate) {
            return null;
        }
        $min = ($ate->getTimestamp() - $de->getTimestamp()) / 60;

        return $min < 0 || $min > self::TEMPO_MAXIMO_MIN ? null : $min;
    }

    /** Tempo de cada etapa: média, mediana, o mais demorado e quantos pedidos entraram na conta. */
    private function tempos(Collection $pedidos, Collection $anterior): array
    {
        $etapas = [
            'aceite'  => ['Aceite', 'do pedido chegar até a loja aceitar', fn ($p) => $this->minutos($p->criado_ifood_em, $p->confirmado_em)],
            // Agendado: o preparo começa perto do horário marcado, então fica fora do tempo de preparo e do total
            'preparo' => ['Preparo', 'do aceite até ficar pronto ou sair para entrega', fn ($p) => $p->momento === 'SCHEDULED' ? null
                : $this->minutos($p->confirmado_em, $p->despachado_em ?? $p->pronto_em)],
            'entrega' => ['Entrega', 'de sair para entrega até o iFood concluir', fn ($p) => $p->tipo !== 'DELIVERY' ? null
                : $this->minutos($p->despachado_em, $p->concluido_em)],
            'total'   => ['Total', 'do pedido chegar até o iFood concluir', fn ($p) => $p->momento === 'SCHEDULED' ? null
                : $this->minutos($p->criado_ifood_em, $p->concluido_em)],
        ];

        $calcular = function (Collection $lista, callable $medir) {
            $valores = $lista->reject(fn ($p) => $this->cancelado($p))->map($medir)->filter(fn ($v) => $v !== null)->sort()->values();
            if ($valores->isEmpty()) {
                return null;
            }
            $n = $valores->count();
            $mediana = $n % 2 ? $valores[intdiv($n, 2)] : ($valores[$n / 2 - 1] + $valores[$n / 2]) / 2;

            return ['media' => round($valores->avg(), 1), 'mediana' => round($mediana, 1), 'maximo' => round($valores->last(), 1), 'pedidos' => $n];
        };

        $resultado = [];
        foreach ($etapas as $chave => [$rotulo, $explica, $medir]) {
            $atual = $calcular($pedidos, $medir);
            $antes = $calcular($anterior, $medir);
            $resultado[$chave] = [
                'rotulo'   => $rotulo,
                'explica'  => $explica,
                'valores'  => $atual,
                'anterior' => $antes['media'] ?? null,
                // Em minutos: negativo = ficou mais rápido
                'variacao' => $atual && $antes ? round($atual['media'] - $antes['media'], 1) : null,
            ];
        }

        return $resultado;
    }

    /** Pedidos (sem os cancelados) e faturamento de cada dia do período. */
    private function porDia(Collection $pedidos, string $inicio, string $fim): array
    {
        $grupos = $pedidos->reject(fn ($p) => $this->cancelado($p))->groupBy(fn ($p) => $p->quando->toDateString());
        $cancelados = $pedidos->filter(fn ($p) => $this->cancelado($p))->groupBy(fn ($p) => $p->quando->toDateString());
        $dias = [];
        for ($d = Carbon::parse($inicio); $d->lte(Carbon::parse($fim)); $d->addDay()) {
            $chave = $d->toDateString();
            $lista = $grupos->get($chave, collect());
            $dias[] = [
                'data'        => $chave,
                'rotulo'      => $d->format('d/m'),
                'semana'      => self::DIAS_SEMANA[$d->dayOfWeek],
                'pedidos'     => $lista->count(),
                'faturamento' => round((float) $lista->sum('total'), 2),
                'cancelados'  => $cancelados->get($chave, collect())->count(),
                'fim_de_semana' => $d->isWeekend(),
                'futuro'      => $d->isAfter(today()),
            ];
        }

        return $dias;
    }

    /** Pedidos por hora do dia (0 a 23), sem os cancelados. */
    private function porHora(Collection $pedidos): array
    {
        $grupos = $pedidos->reject(fn ($p) => $this->cancelado($p))->groupBy(fn ($p) => (int) $p->quando->format('G'));

        return collect(range(0, 23))->map(fn ($h) => [
            'hora'        => $h,
            'rotulo'      => sprintf('%02dh', $h),
            'pedidos'     => $grupos->get($h, collect())->count(),
            'faturamento' => round((float) $grupos->get($h, collect())->sum('total'), 2),
        ])->all();
    }

    /** Dia da semana × hora: quantos pedidos (sem os cancelados), para achar os horários de pico. */
    private function mapaCalor(Collection $pedidos): array
    {
        $contagem = $pedidos->reject(fn ($p) => $this->cancelado($p))
            ->countBy(fn ($p) => $p->quando->dayOfWeek . '-' . (int) $p->quando->format('G'));
        $maximo = (int) ($contagem->max() ?? 0);

        // Só as horas que tiveram algum pedido (com uma margem), para o mapa não ficar cheio de colunas vazias
        $horas = $contagem->keys()->map(fn ($k) => (int) explode('-', $k)[1]);
        $de = $horas->isEmpty() ? 10 : max(0, $horas->min() - 1);
        $ate = $horas->isEmpty() ? 23 : min(23, $horas->max() + 1);

        $linhas = [];
        foreach (self::DIAS_SEMANA as $dia => $rotulo) {
            $celulas = [];
            for ($h = $de; $h <= $ate; $h++) {
                $n = (int) ($contagem["{$dia}-{$h}"] ?? 0);
                $celulas[] = ['hora' => $h, 'pedidos' => $n, 'nivel' => $n === 0 || !$maximo ? 0 : (int) ceil($n / $maximo * 5)];
            }
            $linhas[] = ['dia' => $rotulo, 'celulas' => $celulas, 'total' => array_sum(array_column($celulas, 'pedidos'))];
        }

        $pico = $contagem->sortDesc()->keys()->first();
        [$picoDia, $picoHora] = $pico !== null ? array_map('intval', explode('-', $pico)) : [null, null];

        return [
            'horas'  => range($de, $ate),
            'linhas' => $linhas,
            'maximo' => $maximo,
            'pico'   => $pico !== null ? [
                'texto'   => self::DIAS_SEMANA[$picoDia] . ', ' . sprintf('%02dh às %02dh', $picoHora, ($picoHora + 1) % 24),
                'pedidos' => $maximo,
            ] : null,
        ];
    }

    private function porTipo(Collection $pedidos): array
    {
        return $pedidos->groupBy(fn ($p) => $p->tipo === 'DELIVERY' ? 'Entrega' : 'Retirada')
            ->map(function (Collection $lista, string $rotulo) use ($pedidos) {
                $validos = $lista->reject(fn ($p) => $this->cancelado($p));

                return [
                    'tipo'        => $rotulo,
                    'pedidos'     => $validos->count(),
                    'cancelados'  => $lista->count() - $validos->count(),
                    'faturamento' => round((float) $validos->sum('total'), 2),
                    'ticket'      => $validos->count() ? round((float) $validos->sum('total') / $validos->count(), 2) : null,
                    'participacao' => $pedidos->count() ? round($lista->count() / $pedidos->count() * 100, 1) : 0,
                ];
            })->sortByDesc('pedidos')->values()->all();
    }

    /** Cancelamentos por quem cancelou e pelo motivo. */
    private function cancelamentos(Collection $pedidos): array
    {
        $cancelados = $pedidos->filter(fn ($p) => $this->cancelado($p));

        return [
            'por_quem' => [
                ['rotulo' => 'Pela loja', 'pedidos' => $cancelados->where('cancelado_por', 'loja')->count()],
                ['rotulo' => 'Pelo iFood ou cliente', 'pedidos' => $cancelados->where('cancelado_por', '!=', 'loja')->count()],
            ],
            'motivos' => $cancelados->groupBy(fn ($p) => trim((string) $p->motivo_cancelamento) ?: 'Motivo não informado')
                ->map(fn (Collection $l, string $motivo) => [
                    'motivo'  => $motivo,
                    'pedidos' => $l->count(),
                    'valor'   => round((float) $l->sum('total'), 2),
                ])->sortByDesc('pedidos')->take(8)->values()->all(),
        ];
    }

    /** Os 10 produtos mais vendidos (itens dos pedidos não cancelados). */
    private function maisVendidos(Collection $pedidos): array
    {
        $ids = $pedidos->reject(fn ($p) => $this->cancelado($p))->pluck('comanda_pedido_id')->filter()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $itens = collect();
        foreach ($ids->chunk(1000) as $lote) {
            $itens = $itens->concat(DB::table('comanda_pedido_itens')->whereIn('pedido_id', $lote->all())
                ->selectRaw('nome, SUM(quantidade) as quantidade, SUM(subtotal) as valor, COUNT(DISTINCT pedido_id) as pedidos')
                ->groupBy('nome')->get());
        }

        $total = (float) $itens->sum('valor');

        return $itens->groupBy('nome')->map(fn (Collection $l, string $nome) => [
            'nome'       => $nome,
            'quantidade' => (float) $l->sum('quantidade'),
            'valor'      => round((float) $l->sum('valor'), 2),
            'pedidos'    => (int) $l->sum('pedidos'),
        ])->sortByDesc('quantidade')->take(10)->values()
            ->map(fn ($p) => $p + ['participacao' => $total > 0 ? round($p['valor'] / $total * 100, 1) : 0])->all();
    }
}
