@extends('layouts.app')

@section('titulo', 'Indicadores iFood')
@section('body-class', 'conteudo-largo')

@section('breadcrumb')
    <span>Comandas</span>
    <span class="text-gray-300">›</span>
    <span>Relatórios</span>
    <span class="text-gray-300">›</span>
    <span class="text-gray-700 font-medium">Indicadores iFood</span>
@endsection

@php
    $r = $dados['resumo'];
    $var = $dados['variacao'];
    $dinheiro = fn ($v) => $v === null ? '—' : 'R$ ' . number_format((float) $v, 2, ',', '.');
    $inteiro = fn ($v) => number_format((float) $v, 0, ',', '.');
    $perc = fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', '.') . '%';
    // Minutos → "12 min" / "1h 05min"
    $tempo = function ($min) {
        if ($min === null) return '—';
        $min = (int) round($min);
        return $min < 60 ? "{$min} min" : intdiv($min, 60) . 'h ' . str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT) . 'min';
    };
    // Variação: ▲/▼ e cor. $melhorSobe = true quando subir é bom (pedidos, faturamento).
    $seta = function ($v, bool $melhorSobe, string $unidade = '%') {
        if ($v === null) return ['', 'text-gray-400', 'sem dados para comparar'];
        if (abs($v) < 0.05) return ['=', 'text-gray-500', 'igual'];
        $bom = ($v > 0) === $melhorSobe;
        $valor = number_format(abs($v), 1, ',', '.') . $unidade;
        return [($v > 0 ? '▲ ' : '▼ ') . $valor, $bom ? 'text-emerald-700' : 'text-red-600', ''];
    };
    $periodoAnterior = \Carbon\Carbon::parse($dados['anterior']['inicio'])->format('d/m') . ' a ' . \Carbon\Carbon::parse($dados['anterior']['fim'])->format('d/m');
    $niveis = ['bg-gray-100 text-gray-300', 'bg-emerald-100 text-emerald-900', 'bg-emerald-200 text-emerald-900', 'bg-emerald-400 text-white', 'bg-emerald-500 text-white', 'bg-emerald-700 text-white'];

    $maiorDia = collect($dados['por_dia'])->max('pedidos');
    $barrasDia = collect($dados['por_dia'])->map(fn ($d) => [
        'rotulo'   => $d['rotulo'],
        'valor'    => $d['pedidos'],
        'destaque' => $maiorDia > 0 && $d['pedidos'] === $maiorDia,
        'apagado'  => $d['futuro'],
        'tooltip'  => "{$d['semana']} {$d['rotulo']}: {$d['pedidos']} pedido(s) · " . $dinheiro($d['faturamento'])
            . ($d['cancelados'] ? " · {$d['cancelados']} cancelado(s)" : ''),
    ])->all();
    $maiorHora = collect($dados['por_hora'])->max('pedidos');
    $barrasHora = collect($dados['por_hora'])->map(fn ($h) => [
        'rotulo'   => (string) $h['hora'],
        'valor'    => $h['pedidos'],
        'destaque' => $maiorHora > 0 && $h['pedidos'] === $maiorHora,
        'tooltip'  => sprintf('%02dh às %02dh', $h['hora'], ($h['hora'] + 1) % 24) . ": {$h['pedidos']} pedido(s) · " . $dinheiro($h['faturamento']),
    ])->all();
@endphp

@section('conteudo')
    <div class="flex items-center gap-4 mb-4">
        <p class="flex-1 min-w-0 text-xs text-gray-500">
            Desempenho da loja no iFood. As setas comparam com o período anterior do mesmo tamanho ({{ $periodoAnterior }}).
        </p>
        <a href="{{ route('ifood.indicadores.exportar-excel', request()->query()) }}"
           class="shrink-0 whitespace-nowrap bg-green-600 hover:bg-green-500 text-white px-4 py-2.5 rounded-lg font-semibold text-sm transition">
            Exportar Excel
        </a>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('ifood.indicadores.index') }}" class="flex flex-wrap items-end gap-3 mb-4 bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <label class="text-xs font-medium uppercase tracking-wide text-gray-500">De
            <input type="date" name="data_inicio" value="{{ $f['data_inicio'] }}"
                   class="mt-1 block bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
        </label>
        <label class="text-xs font-medium uppercase tracking-wide text-gray-500">Até
            <input type="date" name="data_fim" value="{{ $f['data_fim'] }}"
                   class="mt-1 block bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
        </label>
        <label class="text-xs font-medium uppercase tracking-wide text-gray-500">Tipo
            <select name="tipo" class="mt-1 block bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
                @foreach ($tipos as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected($f['tipo'] === $valor)>{{ $rotulo }}</option>
                @endforeach
            </select>
        </label>
        <button class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2 rounded-lg font-semibold text-sm transition">Aplicar</button>
        <p class="basis-full text-xs text-gray-400">Pedidos pela data do pedido no iFood. Por padrão, o mês atual.</p>
    </form>

    {{-- Números principais --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
        @php [$sPed, $cPed] = $seta($var['pedidos'], true); @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-gray-500">Pedidos</p>
            <p class="text-2xl font-semibold text-gray-800">{{ $inteiro($r['pedidos']) }}</p>
            <p class="text-xs text-gray-400">@if ($sPed)<span class="{{ $cPed }} font-medium">{{ $sPed }}</span> · @endif{{ $inteiro($r['concluidos']) }} concluídos</p>
        </div>
        @php [$sFat, $cFat] = $seta($var['faturamento'], true); @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-gray-500">Faturamento</p>
            <p class="text-2xl font-semibold text-emerald-700">{{ $dinheiro($r['faturamento']) }}</p>
            <p class="text-xs text-gray-400">@if ($sFat)<span class="{{ $cFat }} font-medium">{{ $sFat }}</span> · @endif total dos pedidos</p>
        </div>
        @php [$sTic, $cTic] = $seta($var['ticket'], true); @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-gray-500">Ticket médio</p>
            <p class="text-2xl font-semibold text-gray-800">{{ $dinheiro($r['ticket']) }}</p>
            <p class="text-xs text-gray-400">@if ($sTic)<span class="{{ $cTic }} font-medium">{{ $sTic }}</span> · @endif por pedido</p>
        </div>
        @php [$sCan, $cCan] = $seta($var['cancelamento'], false, ' p.p.'); @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-gray-500">Cancelamentos</p>
            <p class="text-2xl font-semibold {{ $r['cancelados'] ? 'text-red-600' : 'text-gray-800' }}">{{ $perc($r['taxa_cancelamento']) }}</p>
            <p class="text-xs text-gray-400">@if ($sCan)<span class="{{ $cCan }} font-medium">{{ $sCan }}</span> · @endif{{ $inteiro($r['cancelados']) }} de {{ $inteiro($r['recebidos']) }} · {{ $dinheiro($r['valor_cancelado']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3 col-span-2 lg:col-span-1">
            <p class="text-xs uppercase tracking-wide text-gray-500">Horário de pico</p>
            <p class="text-2xl font-semibold text-gray-800">{{ $dados['mapa']['pico']['texto'] ?? '—' }}</p>
            <p class="text-xs text-gray-400">{{ $dados['mapa']['pico'] ? $dados['mapa']['pico']['pedidos'] . ' pedido(s) nessa hora no período' : 'sem pedidos no período' }}</p>
        </div>
    </div>

    {{-- Tempos de cada etapa --}}
    <h2 class="text-sm font-semibold text-gray-700 mb-2">Tempos dos pedidos</h2>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        @foreach ($dados['tempos'] as $t)
            @php
                $v = $t['valores'];
                [$sT, $cT] = $seta($t['variacao'], false, ' min');
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-gray-500">{{ $t['rotulo'] }}</p>
                <p class="text-2xl font-semibold text-gray-800">{{ $tempo($v['media'] ?? null) }} <span class="text-xs font-normal text-gray-400">em média</span></p>
                <p class="text-xs text-gray-500">{{ $t['explica'] }}</p>
                @if ($v)
                    <p class="mt-1 text-xs text-gray-400">
                        @if ($sT)<span class="{{ $cT }} font-medium">{{ $sT }}</span> · @endif
                        metade em até {{ $tempo($v['mediana']) }} · mais demorado {{ $tempo($v['maximo']) }} · {{ $inteiro($v['pedidos']) }} pedido(s)
                    </p>
                @else
                    <p class="mt-1 text-xs text-gray-400">Sem pedidos com essa etapa no período.</p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Pedidos por dia --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-4 mb-6">
        <h2 class="text-sm font-semibold text-gray-700">Pedidos por dia</h2>
        <p class="text-xs text-gray-400 mb-3">Sem os cancelados. A barra mais escura é o melhor dia. Passe o mouse sobre uma barra para ver o faturamento.</p>
        <div class="overflow-x-auto">
            <div class="min-w-[640px]">
                @include('comandas.relatorios.ifood-indicadores._barras', ['barras' => $barrasDia, 'titulo' => 'Pedidos por dia', 'cadaRotulo' => count($barrasDia) > 16 ? 2 : 1])
            </div>
        </div>
    </div>

    {{-- Horários de pico --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-4">
            <h2 class="text-sm font-semibold text-gray-700">Pedidos por hora do dia</h2>
            <p class="text-xs text-gray-400 mb-3">Soma de todos os dias do período. A barra mais escura é a hora com mais pedidos.</p>
            <div class="overflow-x-auto">
                <div class="min-w-[480px]">
                    @include('comandas.relatorios.ifood-indicadores._barras', ['barras' => $barrasHora, 'titulo' => 'Pedidos por hora', 'cadaRotulo' => 1, 'larguraAlvo' => 600])
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-4">
            <h2 class="text-sm font-semibold text-gray-700">Mapa de pico (dia da semana × hora)</h2>
            <p class="text-xs text-gray-400 mb-3">Quanto mais escuro, mais pedidos. O número é a quantidade de pedidos naquela hora, somando o período.</p>
            <div class="overflow-x-auto">
                <table class="text-[11px] border-separate border-spacing-0.5">
                    <thead>
                        <tr>
                            <th></th>
                            @foreach ($dados['mapa']['horas'] as $h)
                                <th class="font-normal text-gray-400 w-8 text-center">{{ $h }}h</th>
                            @endforeach
                            <th class="font-normal text-gray-400 pl-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dados['mapa']['linhas'] as $linha)
                            <tr>
                                <th class="font-medium text-gray-600 pr-2 text-left">{{ $linha['dia'] }}</th>
                                @foreach ($linha['celulas'] as $c)
                                    <td class="w-8 h-8 rounded text-center font-medium {{ $niveis[$c['nivel']] }}"
                                        title="{{ $linha['dia'] }}, {{ sprintf('%02dh às %02dh', $c['hora'], ($c['hora'] + 1) % 24) }}: {{ $c['pedidos'] }} pedido(s)">
                                        {{ $c['pedidos'] ?: '' }}
                                    </td>
                                @endforeach
                                <td class="pl-2 text-right font-semibold text-gray-700">{{ $linha['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3 flex items-center gap-1.5 text-xs text-gray-500">
                <span>Menos</span>
                @foreach (array_slice($niveis, 0, 6) as $nivel)
                    <span class="inline-block w-4 h-4 rounded {{ explode(' ', $nivel)[0] }}"></span>
                @endforeach
                <span>Mais</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
        {{-- Entrega × retirada --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <h2 class="px-4 pt-4 pb-3 text-sm font-semibold text-gray-700">Entrega e retirada</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm whitespace-nowrap">
                    <thead class="bg-[#10284A] text-xs uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-3 text-left font-medium text-amber-50">Tipo</th>
                            <th class="px-3 py-3 text-right font-medium text-amber-50">Pedidos</th>
                            <th class="px-3 py-3 text-right font-medium text-amber-50">Cancelados</th>
                            <th class="px-3 py-3 text-right font-medium text-amber-50">Faturamento</th>
                            <th class="px-3 py-3 text-right font-medium text-amber-50">Ticket médio</th>
                            <th class="px-3 py-3 text-right font-medium text-amber-50">% dos pedidos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($dados['por_tipo'] as $t)
                            <tr class="hover:bg-gray-100 transition">
                                <td class="px-3 py-3 font-medium text-gray-800">{{ $t['tipo'] }}</td>
                                <td class="px-3 py-3 text-right">{{ $inteiro($t['pedidos']) }}</td>
                                <td class="px-3 py-3 text-right {{ $t['cancelados'] ? 'text-red-600' : 'text-gray-400' }}">{{ $inteiro($t['cancelados']) }}</td>
                                <td class="px-3 py-3 text-right">{{ $dinheiro($t['faturamento']) }}</td>
                                <td class="px-3 py-3 text-right">{{ $dinheiro($t['ticket']) }}</td>
                                <td class="px-3 py-3 text-right">{{ $perc($t['participacao']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Nenhum pedido no período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Cancelamentos --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-4 pt-4 pb-3 flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-sm font-semibold text-gray-700">Cancelamentos</h2>
                <p class="text-xs text-gray-500">
                    @foreach ($dados['cancelamentos']['por_quem'] as $q)
                        {{ $q['rotulo'] }}: <span class="font-semibold text-gray-700">{{ $q['pedidos'] }}</span>@if (!$loop->last) · @endif
                    @endforeach
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-[#10284A] text-xs uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-amber-50">Motivo</th>
                            <th class="px-4 py-3 text-right font-medium text-amber-50">Pedidos</th>
                            <th class="px-4 py-3 text-right font-medium text-amber-50">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($dados['cancelamentos']['motivos'] as $m)
                            <tr class="hover:bg-gray-100 transition">
                                <td class="px-4 py-3 text-gray-800">{{ $m['motivo'] }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">{{ $inteiro($m['pedidos']) }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap text-red-600">{{ $dinheiro($m['valor']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-8 text-center text-gray-400">Nenhum pedido cancelado no período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Mais vendidos --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <h2 class="px-4 pt-4 pb-3 text-sm font-semibold text-gray-700">Os 10 mais vendidos no iFood</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead class="bg-[#10284A] text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">#</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Produto</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Quantidade</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Pedidos</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Valor</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">% do valor vendido</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($dados['produtos'] as $i => $p)
                        <tr class="hover:bg-gray-100 transition">
                            <td class="px-4 py-3 text-gray-400">{{ $i + 1 }}</td>
                            <td class="px-4 py-3 font-medium text-gray-800 max-w-80 truncate" title="{{ $p['nome'] }}">{{ $p['nome'] }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($p['quantidade'], fmod($p['quantidade'], 1) ? 2 : 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">{{ $inteiro($p['pedidos']) }}</td>
                            <td class="px-4 py-3 text-right">{{ $dinheiro($p['valor']) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 min-w-36">
                                    <progress max="100" value="{{ $p['participacao'] }}"
                                              class="flex-1 h-1.5 rounded-full overflow-hidden appearance-none [&::-webkit-progress-bar]:bg-gray-100 [&::-webkit-progress-value]:bg-emerald-500 [&::-moz-progress-bar]:bg-emerald-500 bg-gray-100"></progress>
                                    <span class="text-xs text-gray-600 w-12 text-right">{{ $perc($p['participacao']) }}</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Nenhum item vendido no período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="mt-3 text-xs text-gray-400">
        Pedidos, faturamento, ticket médio, horários e mais vendidos não contam os cancelados. Os tempos usam o horário de cada etapa informado pelo iFood;
        pedidos agendados ficam fora do tempo de preparo e do total, e tempos acima de 6 horas são desconsiderados.
        O tempo de entrega vai até o iFood concluir o pedido, que pode acontecer um pouco depois da entrega ao cliente.
        Pedidos anteriores a esta versão podem não ter todas as etapas registradas.
    </p>
@endsection
