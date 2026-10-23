@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $moeda = fn ($v) => number_format((float) $v, 2, ',', '.');
    $data = fn ($v, $formato = 'd/m/Y H:i') => $v ? \Carbon\Carbon::parse($v)->format($formato) : '—';
    $colunas = 17;

    $tipoClasse = [
        'NFC-e' => 'bg-emerald-50 text-emerald-700',
        'NF-e'  => 'bg-blue-50 text-blue-700',
    ];
@endphp

<div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Documentos</p>
        <p class="text-lg font-semibold text-gray-800">{{ number_format($totais->documentos, 0, ',', '.') }}</p>
        <p class="text-xs text-gray-400">{{ number_format($totais->itens, 0, ',', '.') }} {{ $totais->itens == 1 ? 'item' : 'itens' }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Valor bruto</p>
        <p class="text-lg font-semibold text-gray-800">R$ {{ $moeda($totais->valor_bruto) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Descontos</p>
        <p class="text-lg font-semibold text-red-600">R$ {{ $moeda($totais->desconto) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Valor líquido</p>
        <p class="text-lg font-semibold text-emerald-700">R$ {{ $moeda($totais->valor_liquido) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Impostos destacados</p>
        <p class="text-lg font-semibold text-amber-600">R$ {{ $moeda($totais->impostos) }}</p>
    </div>
</div>

@if ($totais->sem_impostos > 0)
    <p class="mb-4 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
        {{ number_format($totais->sem_impostos, 0, ',', '.') }} {{ $totais->sem_impostos == 1 ? 'item' : 'itens' }}
        de notas emitidas antes do registro dos impostos aparecem com “—” nas colunas de impostos e não entram nos totais de impostos.
    </p>
@endif

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Data</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Tipo</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Nº doc.</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Cód.</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Descrição</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Qtd</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Valor bruto</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Desc.</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Acrésc.</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Valor líquido</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">ICMS</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">PIS</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">COFINS</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">IPI</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">IBS</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">CBS</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Vendedor</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($itens as $item)
                    @php
                        $imposto = fn ($v) => $item->tem_impostos ? $moeda($v) : '—';
                    @endphp
                    <tr class="hover:bg-gray-200 transition">
                        <td class="px-4 py-3 text-gray-600">{{ $data($item->data) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $tipoClasse[$item->tipo] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $item->tipo }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-800">
                            {{ $item->numero ?? '—' }}@if ($item->serie !== null && $item->serie !== '')<span class="text-xs font-normal text-gray-400"> / {{ $item->serie }}</span>@endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $item->codigo ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-800 max-w-xs truncate" title="{{ $item->descricao }}">{{ $item->descricao ?: '—' }}</td>
                        <td class="px-4 py-3 text-right text-gray-700">{{ $qtd($item->quantidade) }}</td>
                        <td class="px-4 py-3 text-right text-gray-700">{{ $moeda($item->valor_bruto) }}</td>
                        <td class="px-4 py-3 text-right {{ (float) $item->desconto > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ $moeda($item->desconto) }}</td>
                        <td class="px-4 py-3 text-right {{ (float) $item->acrescimo > 0 ? 'text-gray-700' : 'text-gray-400' }}">{{ $moeda($item->acrescimo) }}</td>
                        <td class="px-4 py-3 text-right font-medium text-gray-800">{{ $moeda($item->valor_liquido) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ $imposto($item->icms) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ $imposto($item->pis) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ $imposto($item->cofins) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ $imposto($item->ipi) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ $imposto($item->ibs) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ $imposto($item->cbs) }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $item->vendedor ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $colunas }}" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhuma venda emitida encontrada.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($itens->count() > 0)
                <tfoot class="bg-gray-50 border-t border-gray-200 text-sm font-semibold text-gray-800">
                    <tr>
                        <td class="px-4 py-3" colspan="5">Total do filtro</td>
                        <td class="px-4 py-3 text-right">{{ $qtd($totais->quantidade) }}</td>
                        <td class="px-4 py-3 text-right">{{ $moeda($totais->valor_bruto) }}</td>
                        <td class="px-4 py-3 text-right text-red-600">{{ $moeda($totais->desconto) }}</td>
                        <td class="px-4 py-3 text-right">{{ $moeda($totais->acrescimo) }}</td>
                        <td class="px-4 py-3 text-right">{{ $moeda($totais->valor_liquido) }}</td>
                        <td class="px-4 py-3 text-right">{{ $moeda($totais->icms) }}</td>
                        <td class="px-4 py-3 text-right">{{ $moeda($totais->pis) }}</td>
                        <td class="px-4 py-3 text-right">{{ $moeda($totais->cofins) }}</td>
                        <td class="px-4 py-3 text-right">{{ $moeda($totais->ipi) }}</td>
                        <td class="px-4 py-3 text-right">{{ $moeda($totais->ibs) }}</td>
                        <td class="px-4 py-3 text-right">{{ $moeda($totais->cbs) }}</td>
                        <td class="px-4 py-3"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

<div class="mt-4">
    {{ $itens->links() }}
</div>
