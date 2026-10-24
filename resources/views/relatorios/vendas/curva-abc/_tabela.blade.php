@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $moeda = fn ($v) => number_format((float) $v, 2, ',', '.');
    $perc = fn ($v) => number_format((float) $v, 2, ',', '.') . '%';
    $colunas = 9;

    $classeEstilo = [
        'A' => ['badge' => 'bg-emerald-50 text-emerald-700', 'barra' => 'bg-emerald-500', 'valor' => 'text-emerald-700'],
        'B' => ['badge' => 'bg-amber-50 text-amber-700',     'barra' => 'bg-amber-500',   'valor' => 'text-amber-600'],
        'C' => ['badge' => 'bg-gray-100 text-gray-600',      'barra' => 'bg-gray-400',    'valor' => 'text-gray-700'],
    ];

    $explicacao = [
        'A' => 'Maior faturamento',
        'B' => 'Intermediário',
        'C' => 'Menor expressão',
    ];
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    @foreach (['A', 'B', 'C'] as $letra)
        @php $c = $resumo->classes[$letra]; @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-gray-500 flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-semibold {{ $classeEstilo[$letra]['badge'] }}">{{ $letra }}</span>
                {{ $explicacao[$letra] }}
            </p>
            <p class="text-lg font-semibold {{ $classeEstilo[$letra]['valor'] }}">R$ {{ $moeda($c->faturamento) }}</p>
            <p class="text-xs text-gray-400">
                {{ $perc($c->percentual_faturamento) }} do faturamento ·
                {{ number_format($c->produtos, 0, ',', '.') }} {{ $c->produtos == 1 ? 'produto' : 'produtos' }} ({{ $perc($c->percentual_produtos) }})
            </p>
        </div>
    @endforeach

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Total do período</p>
        <p class="text-lg font-semibold text-gray-800">R$ {{ $moeda($resumo->faturamento) }}</p>
        <p class="text-xs text-gray-400">{{ number_format($resumo->produtos, 0, ',', '.') }} {{ $resumo->produtos == 1 ? 'produto vendido' : 'produtos vendidos' }}</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">#</th>
                    <th class="px-4 py-3 text-center font-medium text-amber-50">Classe</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Código</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Produto</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Qtd vendida</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Nº de vendas</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Faturamento</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">% do total</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">% acumulado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($produtos as $p)
                    @php $estilo = $classeEstilo[$p->classe]; @endphp
                    <tr class="hover:bg-gray-200 transition">
                        <td class="px-4 py-3 text-gray-400">{{ $p->posicao }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-semibold {{ $estilo['badge'] }}">{{ $p->classe }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $p->codigo ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-800 font-medium max-w-xs truncate" title="{{ $p->nome }}">{{ $p->nome ?: '—' }}</td>
                        <td class="px-4 py-3 text-right text-gray-700">{{ $qtd($p->quantidade) }}</td>
                        <td class="px-4 py-3 text-right text-gray-700">{{ number_format($p->vendas, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right font-medium text-gray-800">{{ $moeda($p->faturamento) }}</td>
                        <td class="px-4 py-3 text-right text-gray-700">{{ $perc($p->percentual) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2 min-w-36">
                                <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full {{ $estilo['barra'] }}" style="width: {{ min(100, max(0, (float) $p->percentual_acumulado)) }}%"></div>
                                </div>
                                <span class="text-xs text-gray-600 w-14 text-right">{{ $perc($p->percentual_acumulado) }}</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $colunas }}" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhum produto vendido no período.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<p class="mt-3 text-xs text-gray-400">
    Faturamento = valor líquido vendido (produtos + acréscimos − descontos) de NFC-e e NF-e emitidas.
    Classe A: até {{ rtrim(rtrim(number_format($filtros['limite_a'], 2, ',', ''), '0'), ',') }}% do faturamento acumulado;
    B: até {{ rtrim(rtrim(number_format($filtros['limite_b'], 2, ',', ''), '0'), ',') }}%; C: o restante.
    A classe vale para o ranking do período inteiro, mesmo quando a lista está filtrada.
</p>

<div class="mt-4">
    {{ $produtos->links() }}
</div>
