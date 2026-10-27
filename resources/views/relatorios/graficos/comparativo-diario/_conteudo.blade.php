@php
    $moeda = fn ($v) => number_format((float) $v, 2, ',', '.');
    $perc = fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', '.') . '%';
    $sinal = fn ($v) => $v === null ? '' : ((float) $v > 0 ? '+' : '');
    $corVar = fn ($v) => $v === null ? 'text-gray-400' : ((float) $v < 0 ? 'text-red-600' : ((float) $v > 0 ? 'text-emerald-700' : 'text-gray-500'));
    $comparar = $filtros['comparar'] !== 'nao';
@endphp

{{-- Cards --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Total no período</p>
        <p class="text-lg font-semibold text-emerald-700">R$ {{ $moeda($totais->valor_liquido) }}</p>
        <p class="text-xs text-gray-400">
            {{ $totais->dias }} {{ $totais->dias == 1 ? 'dia' : 'dias' }} ({{ $totais->dias_com_venda }} com venda) · {{ number_format($totais->documentos, 0, ',', '.') }} documentos
            @if ($totais->variacao_comparacao !== null)
                · <span class="{{ $corVar($totais->variacao_comparacao) }}">{{ $sinal($totais->variacao_comparacao) }}{{ $perc($totais->variacao_comparacao) }} s/ {{ $comparacao_curto }}</span>
            @endif
        </p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Média diária</p>
        <p class="text-lg font-semibold text-gray-800">R$ {{ $moeda($totais->media_diaria) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Melhor dia</p>
        <p class="text-lg font-semibold text-gray-800">{{ $totais->melhor_dia ?: '—' }}</p>
        <p class="text-xs text-gray-400">{{ $totais->melhor_dia ? 'R$ ' . $moeda($totais->melhor_valor) : '' }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Último dia vs anterior</p>
        <p class="text-lg font-semibold {{ $corVar($totais->ultimo_variacao) }}">{{ $sinal($totais->ultimo_variacao) }}{{ $perc($totais->ultimo_variacao) }}</p>
        <p class="text-xs text-gray-400">{{ $totais->ultimo_rotulo }}{{ $totais->ultimo_parcial ? ' (em andamento)' : '' }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Ticket médio</p>
        <p class="text-lg font-semibold text-gray-800">R$ {{ $moeda($totais->ticket_medio) }}</p>
        <p class="text-xs text-gray-400">por documento</p>
    </div>
</div>

{{-- Gráfico de barras verticais (no topo) --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-4 mb-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-3">Evolução diária — {{ $grafico['metrica'] }}</h2>
    <div class="overflow-x-auto">
        @include('relatorios.graficos.comparativo-mensal._grafico', ['grafico' => $grafico])
    </div>
</div>

{{-- Tabela de cada dia (logo abaixo do gráfico) --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Dia</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Documentos</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Itens</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Valor bruto</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Desc.</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Acrésc.</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Valor líquido</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Ticket médio</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Var. R$ s/ dia ant.</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Var. % s/ dia ant.</th>
                    @if ($comparar)
                        <th class="px-4 py-3 text-right font-medium text-amber-50">{{ $comparacao_titulo }}</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Var. % s/ {{ $comparacao_curto }}</th>
                    @endif
                    <th class="px-4 py-3 text-left font-medium text-amber-50">% do total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($dias as $m)
                    <tr class="hover:bg-gray-200 transition {{ $m['valor_liquido'] <= 0 ? 'text-gray-400' : '' }} {{ $m['fim_de_semana'] ? 'bg-gray-50' : '' }}">
                        <td class="px-4 py-3 font-medium text-gray-800">
                            {{ $m['rotulo'] }}
                            @if ($m['parcial'])
                                <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700">em andamento</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">{{ number_format($m['documentos'], 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($m['itens'], 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right">{{ $moeda($m['valor_bruto']) }}</td>
                        <td class="px-4 py-3 text-right {{ $m['desconto'] > 0 ? 'text-red-600' : '' }}">{{ $moeda($m['desconto']) }}</td>
                        <td class="px-4 py-3 text-right">{{ $moeda($m['acrescimo']) }}</td>
                        <td class="px-4 py-3 text-right font-medium text-gray-800">{{ $moeda($m['valor_liquido']) }}</td>
                        <td class="px-4 py-3 text-right">{{ $moeda($m['ticket_medio']) }}</td>
                        <td class="px-4 py-3 text-right {{ $corVar($m['variacao_valor']) }}">{{ $sinal($m['variacao_valor']) }}{{ $moeda($m['variacao_valor']) }}</td>
                        <td class="px-4 py-3 text-right font-medium {{ $corVar($m['variacao_percentual']) }}">{{ $sinal($m['variacao_percentual']) }}{{ $perc($m['variacao_percentual']) }}</td>
                        @if ($comparar)
                            <td class="px-4 py-3 text-right">{{ $moeda($m['comparacao']['valor_liquido'] ?? 0) }}</td>
                            <td class="px-4 py-3 text-right font-medium {{ $corVar($m['variacao_comparacao_percentual'] ?? null) }}">{{ $sinal($m['variacao_comparacao_percentual'] ?? null) }}{{ $perc($m['variacao_comparacao_percentual'] ?? null) }}</td>
                        @endif
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2 min-w-28">
                                <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ min(100, max(0, $m['participacao'])) }}%"></div>
                                </div>
                                <span class="text-xs text-gray-600 w-12 text-right">{{ $perc($m['participacao']) }}</span>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 border-t border-gray-200 text-sm font-semibold text-gray-800">
                <tr>
                    <td class="px-4 py-3">Total</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totais->documentos, 0, ',', '.') }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totais->itens, 0, ',', '.') }}</td>
                    <td class="px-4 py-3 text-right">{{ $moeda($totais->valor_bruto) }}</td>
                    <td class="px-4 py-3 text-right text-red-600">{{ $moeda($totais->desconto) }}</td>
                    <td class="px-4 py-3 text-right">{{ $moeda($totais->acrescimo) }}</td>
                    <td class="px-4 py-3 text-right">{{ $moeda($totais->valor_liquido) }}</td>
                    <td class="px-4 py-3 text-right">{{ $moeda($totais->ticket_medio) }}</td>
                    <td class="px-4 py-3"></td>
                    <td class="px-4 py-3"></td>
                    @if ($comparar)
                        <td class="px-4 py-3 text-right">{{ $moeda($totais->comparacao ?? 0) }}</td>
                        <td class="px-4 py-3 text-right {{ $corVar($totais->variacao_comparacao) }}">{{ $sinal($totais->variacao_comparacao) }}{{ $perc($totais->variacao_comparacao) }}</td>
                    @endif
                    <td class="px-4 py-3"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<p class="mt-3 text-xs text-gray-400">
    NFC-e e NF-e de saída emitidas, pela data de emissão. Fins de semana com fundo cinza. Valor bruto inclui os acréscimos (frete e outras despesas); valor líquido = valor bruto − descontos.
    A variação compara cada dia com o dia anterior; o dia em andamento ainda está incompleto, então a comparação com ele tende a ser menor.
    A comparação opcional usa o mesmo dia da semana (7 dias antes ou 364 dias antes). Passe o mouse sobre uma barra para ver o valor completo.
</p>
