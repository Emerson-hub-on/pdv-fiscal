@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $moeda = fn ($v) => number_format((float) $v, 2, ',', '.');
    $perc = fn ($v) => number_format((float) $v, 2, ',', '.') . '%';
    $data = fn ($v, $formato = 'd/m/Y H:i') => $v ? \Carbon\Carbon::parse($v)->format($formato) : '—';
    $detalhada = $filtros['visao'] === 'detalhada';

    $tipoClasse = [
        'NFC-e' => 'bg-emerald-50 text-emerald-700',
        'NF-e'  => 'bg-blue-50 text-blue-700',
    ];
@endphp

<div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Venda (líquida)</p>
        <p class="text-lg font-semibold text-emerald-700">R$ {{ $moeda($totais->valor_liquido) }}</p>
        <p class="text-xs text-gray-400">Bruto R$ {{ $moeda($totais->valor_bruto) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Documentos</p>
        <p class="text-lg font-semibold text-gray-800">{{ number_format($totais->documentos, 0, ',', '.') }}</p>
        <p class="text-xs text-gray-400">{{ number_format($totais->itens, 0, ',', '.') }} {{ $totais->itens == 1 ? 'item' : 'itens' }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Ticket médio</p>
        <p class="text-lg font-semibold text-gray-800">R$ {{ $moeda($totais->ticket_medio) }}</p>
        <p class="text-xs text-gray-400">por documento</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Descontos</p>
        <p class="text-lg font-semibold text-red-600">R$ {{ $moeda($totais->desconto) }}</p>
        <p class="text-xs text-gray-400">{{ $perc($totais->desconto_percentual) }} do bruto</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Vendedores</p>
        <p class="text-lg font-semibold text-gray-800">{{ number_format($totais->vendedores, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Maior venda</p>
        <p class="text-lg font-semibold text-gray-800 truncate" title="{{ $totais->melhor_vendedor }}">{{ $totais->melhor_vendedor ?: '—' }}</p>
        <p class="text-xs text-gray-400">{{ $totais->melhor_vendedor ? 'R$ ' . $moeda($totais->melhor_valor) : '' }}</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            @unless ($detalhada)
                {{-- Visão resumida: uma linha por vendedor --}}
                <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Cód.</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Vendedor</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Perfil</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Documentos</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Itens</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Valor bruto</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Desc.</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Acrésc.</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Valor líquido</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Ticket médio</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">% do total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($registros as $v)
                        @php
                            $perfil = collect([$v->is_admin ? 'Administrador' : null, $v->perfil])->filter()->implode(', ');
                            $participacao = $totais->valor_liquido > 0 ? (float) $v->valor_liquido / (float) $totais->valor_liquido * 100 : 0;
                        @endphp
                        <tr class="hover:bg-gray-200 transition">
                            <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ $v->codigo_vendedor ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-800 font-medium">
                                {{ $v->vendedor }}
                                @if ($v->operador_id !== null && ! $v->vendedor_ativo)
                                    <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Inativo</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $perfil !== '' ? $perfil : '—' }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ number_format($v->documentos, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ number_format($v->itens, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $moeda($v->valor_bruto) }}</td>
                            <td class="px-4 py-3 text-right {{ (float) $v->desconto > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ $moeda($v->desconto) }}</td>
                            <td class="px-4 py-3 text-right {{ (float) $v->acrescimo > 0 ? 'text-gray-700' : 'text-gray-400' }}">{{ $moeda($v->acrescimo) }}</td>
                            <td class="px-4 py-3 text-right font-medium text-gray-800">{{ $moeda($v->valor_liquido) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $moeda($v->ticket_medio) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 min-w-32">
                                    <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full bg-emerald-500" style="width: {{ min(100, max(0, $participacao)) }}%"></div>
                                    </div>
                                    <span class="text-xs text-gray-600 w-14 text-right">{{ $perc($participacao) }}</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhuma venda emitida encontrada.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($registros->count() > 0)
                    <tfoot class="bg-gray-50 border-t border-gray-200 text-sm font-semibold text-gray-800">
                        <tr>
                            <td class="px-4 py-3" colspan="3">Total do filtro</td>
                            <td class="px-4 py-3 text-right">{{ number_format($totais->documentos, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($totais->itens, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">{{ $moeda($totais->valor_bruto) }}</td>
                            <td class="px-4 py-3 text-right text-red-600">{{ $moeda($totais->desconto) }}</td>
                            <td class="px-4 py-3 text-right">{{ $moeda($totais->acrescimo) }}</td>
                            <td class="px-4 py-3 text-right">{{ $moeda($totais->valor_liquido) }}</td>
                            <td class="px-4 py-3 text-right">{{ $moeda($totais->ticket_medio) }}</td>
                            <td class="px-4 py-3"></td>
                        </tr>
                    </tfoot>
                @endif
            @else
                {{-- Visão detalhada: uma linha por venda, no lugar do resumo --}}
                <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Data</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Tipo</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Nº doc.</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Vendedor</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Itens</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Valor bruto</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Desc.</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Acrésc.</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Valor líquido</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($registros as $d)
                        <tr class="hover:bg-gray-200 transition">
                            <td class="px-4 py-3 text-gray-600">{{ $data($d->data) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $tipoClasse[$d->tipo] ?? 'bg-gray-100 text-gray-700' }}">{{ $d->tipo }}</span>
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-800">
                                {{ $d->numero ?? '—' }}@if ($d->serie !== null && $d->serie !== '')<span class="text-xs font-normal text-gray-400"> / {{ $d->serie }}</span>@endif
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $d->vendedor }}@if ($d->codigo_vendedor !== null)<span class="text-xs text-gray-400"> · cód. {{ $d->codigo_vendedor }}</span>@endif
                            </td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ number_format($d->itens, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $moeda($d->valor_bruto) }}</td>
                            <td class="px-4 py-3 text-right {{ (float) $d->desconto > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ $moeda($d->desconto) }}</td>
                            <td class="px-4 py-3 text-right {{ (float) $d->acrescimo > 0 ? 'text-gray-700' : 'text-gray-400' }}">{{ $moeda($d->acrescimo) }}</td>
                            <td class="px-4 py-3 text-right font-medium text-gray-800">{{ $moeda($d->valor_liquido) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhuma venda emitida encontrada.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($registros->count() > 0)
                    <tfoot class="bg-gray-50 border-t border-gray-200 text-sm font-semibold text-gray-800">
                        <tr>
                            <td class="px-4 py-3" colspan="4">Total do filtro</td>
                            <td class="px-4 py-3 text-right">{{ number_format($totais->itens, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">{{ $moeda($totais->valor_bruto) }}</td>
                            <td class="px-4 py-3 text-right text-red-600">{{ $moeda($totais->desconto) }}</td>
                            <td class="px-4 py-3 text-right">{{ $moeda($totais->acrescimo) }}</td>
                            <td class="px-4 py-3 text-right">{{ $moeda($totais->valor_liquido) }}</td>
                        </tr>
                    </tfoot>
                @endif
            @endunless
        </table>
    </div>
</div>

<p class="mt-3 text-xs text-gray-400">
    Vendedor = operador que finalizou a venda (NFC-e) ou emitiu a nota (NF-e). Valor bruto inclui os acréscimos (frete e outras despesas); valor líquido = valor bruto − descontos.
    Usuários inativos continuam aparecendo nas vendas que fizeram. O perfil mostrado é o dos acessos ativos hoje.
</p>

<div class="mt-4">
    {{ $registros->links() }}
</div>
