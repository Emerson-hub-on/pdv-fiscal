@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $moeda = fn ($v) => number_format((float) $v, 2, ',', '.');
    $perc = fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', '.') . '%';
    $data = fn ($v, $formato = 'd/m/Y H:i') => $v ? \Carbon\Carbon::parse($v)->format($formato) : '—';
    $detalhada = $filtros['visao'] === 'detalhada';

    $regimeClasse = [
        'simples' => 'bg-emerald-50 text-emerald-700',
        'normal'  => 'bg-blue-50 text-blue-700',
    ];
    $tipoClasse = [
        'NFC-e' => 'bg-emerald-50 text-emerald-700',
        'NF-e'  => 'bg-blue-50 text-blue-700',
    ];
@endphp

<div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Venda (líquida)</p>
        <p class="text-lg font-semibold text-gray-800">R$ {{ $moeda($totais->valor_venda) }}</p>
        <p class="text-xs text-gray-400">{{ number_format($totais->documentos, 0, ',', '.') }} {{ $totais->documentos == 1 ? 'documento' : 'documentos' }} · {{ number_format($totais->itens, 0, ',', '.') }} {{ $totais->itens == 1 ? 'item' : 'itens' }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">ICMS</p>
        <p class="text-lg font-semibold text-gray-800">R$ {{ $moeda($totais->icms) }}</p>
        <p class="text-xs text-gray-400">Base R$ {{ $moeda($totais->bc_icms) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">PIS + COFINS</p>
        <p class="text-lg font-semibold text-gray-800">R$ {{ $moeda($totais->pis + $totais->cofins) }}</p>
        <p class="text-xs text-gray-400">PIS {{ $moeda($totais->pis) }} · COFINS {{ $moeda($totais->cofins) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">IPI</p>
        <p class="text-lg font-semibold text-gray-800">R$ {{ $moeda($totais->ipi) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">IBS + CBS</p>
        <p class="text-lg font-semibold text-gray-800">R$ {{ $moeda($totais->ibs + $totais->cbs) }}</p>
        <p class="text-xs text-gray-400">IBS {{ $moeda($totais->ibs) }} · CBS {{ $moeda($totais->cbs) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Total de impostos</p>
        <p class="text-lg font-semibold text-amber-600">R$ {{ $moeda($totais->total_impostos) }}</p>
        <p class="text-xs text-gray-400">{{ $perc($totais->carga_percentual) }} da venda</p>
    </div>
</div>

<div class="grid grid-cols-2 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Simples Nacional (CSOSN)</p>
        <p class="text-lg font-semibold text-emerald-700">R$ {{ $moeda($totais->venda_simples) }}</p>
        <p class="text-xs text-gray-400">{{ $perc($totais->percentual_simples) }} da venda</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Regime normal (CST)</p>
        <p class="text-lg font-semibold text-blue-700">R$ {{ $moeda($totais->venda_normal) }}</p>
        <p class="text-xs text-gray-400">{{ $perc($totais->percentual_normal) }} da venda</p>
    </div>
</div>

@if ($totais->itens_sem_impostos > 0)
    <p class="mb-4 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
        {{ number_format($totais->itens_sem_impostos, 0, ',', '.') }} {{ $totais->itens_sem_impostos == 1 ? 'item' : 'itens' }}
        de notas emitidas antes do registro dos impostos não têm tributação gravada e ficam fora deste relatório e dos valores acima.
    </p>
@endif

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            @unless ($detalhada)
                {{-- Visão resumida: uma linha por regime + CST/CSOSN + CFOP + alíquota de ICMS --}}
                <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Regime</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">CST/CSOSN</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">CFOP</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Alíq. ICMS</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Itens</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Venda</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">BC ICMS</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">ICMS</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">PIS</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">COFINS</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">IPI</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">IBS</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">CBS</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Total impostos</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">% s/ venda</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($registros as $g)
                        <tr class="hover:bg-gray-200 transition">
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $regimeClasse[$g->regime] ?? 'bg-gray-100 text-gray-600' }}">{{ $regimes[$g->regime] ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-800" title="{{ $descricoes[$g->cst] ?? '' }}">
                                <span class="font-medium">{{ $g->cst ?: '—' }}</span>
                                <span class="text-xs text-gray-400 ml-1">{{ $descricoes[$g->cst] ?? '' }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-700 font-mono text-xs">{{ $g->cfop ?: '—' }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $perc($g->aliquota_icms) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ number_format($g->itens, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-medium text-gray-800">{{ $moeda($g->valor_venda) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $moeda($g->bc_icms) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $moeda($g->icms) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $moeda($g->pis) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $moeda($g->cofins) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $moeda($g->ipi) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $moeda($g->ibs) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $moeda($g->cbs) }}</td>
                            <td class="px-4 py-3 text-right font-medium text-gray-800">{{ $moeda($g->total_impostos) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $perc($g->carga_percentual) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="15" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhuma venda com tributação registrada no período.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($registros->count() > 0)
                    <tfoot class="bg-gray-50 border-t border-gray-200 text-sm font-semibold text-gray-800">
                        <tr>
                            <td class="px-4 py-3" colspan="4">Total do filtro</td>
                            <td class="px-4 py-3 text-right">{{ number_format($totais->itens, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">{{ $moeda($totais->valor_venda) }}</td>
                            <td class="px-4 py-3 text-right">{{ $moeda($totais->bc_icms) }}</td>
                            <td class="px-4 py-3 text-right">{{ $moeda($totais->icms) }}</td>
                            <td class="px-4 py-3 text-right">{{ $moeda($totais->pis) }}</td>
                            <td class="px-4 py-3 text-right">{{ $moeda($totais->cofins) }}</td>
                            <td class="px-4 py-3 text-right">{{ $moeda($totais->ipi) }}</td>
                            <td class="px-4 py-3 text-right">{{ $moeda($totais->ibs) }}</td>
                            <td class="px-4 py-3 text-right">{{ $moeda($totais->cbs) }}</td>
                            <td class="px-4 py-3 text-right">{{ $moeda($totais->total_impostos) }}</td>
                            <td class="px-4 py-3 text-right">{{ $perc($totais->carga_percentual) }}</td>
                        </tr>
                    </tfoot>
                @endif
            @else
                {{-- Visão detalhada: uma linha por item vendido, no lugar do resumo --}}
                <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Data</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Tipo</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Nº doc.</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Cód.</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Descrição</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Regime</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">CST/CSOSN</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">CFOP</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Venda</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">BC ICMS</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Alíq. ICMS</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">ICMS</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">PIS</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">COFINS</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">IPI</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">IBS</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">CBS</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Total impostos</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($registros as $i)
                        <tr class="hover:bg-gray-200 transition">
                            <td class="px-4 py-3 text-gray-600">{{ $data($i->data) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $tipoClasse[$i->tipo] ?? 'bg-gray-100 text-gray-700' }}">{{ $i->tipo }}</span>
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-800">
                                {{ $i->numero ?? '—' }}@if ($i->serie !== null && $i->serie !== '')<span class="text-xs font-normal text-gray-400"> / {{ $i->serie }}</span>@endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $i->codigo ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-800 max-w-xs truncate" title="{{ $i->descricao }}">{{ $i->descricao ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $regimeClasse[$i->regime] ?? 'bg-gray-100 text-gray-600' }}">{{ $regimes[$i->regime] ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-800" title="{{ $descricoes[$i->cst] ?? '' }}">{{ $i->cst ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-700 font-mono text-xs">{{ $i->cfop ?: '—' }}</td>
                            <td class="px-4 py-3 text-right font-medium text-gray-800">{{ $moeda($i->valor_venda) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $moeda($i->bc_icms) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $perc($i->aliquota_icms) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $moeda($i->icms) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $moeda($i->pis) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $moeda($i->cofins) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $moeda($i->ipi) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $moeda($i->ibs) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $moeda($i->cbs) }}</td>
                            <td class="px-4 py-3 text-right font-medium text-gray-800">{{ $moeda($i->total_impostos) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="18" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhuma venda com tributação registrada no período.</td>
                        </tr>
                    @endforelse
                </tbody>
            @endunless
        </table>
    </div>
</div>

<p class="mt-3 text-xs text-gray-400">
    Valores destacados na nota na emissão (NFC-e e NF-e de saída). Regime pelo código gravado no item: CSOSN (3 dígitos) = Simples Nacional; CST (2 dígitos) = regime normal.
    Na NFC-e não há IPI. Empresas do Simples Nacional pagam ICMS, PIS e COFINS no DAS, sem destaque na nota, então esses valores aparecem zerados para elas.
</p>

<div class="mt-4">
    {{ $registros->links() }}
</div>
