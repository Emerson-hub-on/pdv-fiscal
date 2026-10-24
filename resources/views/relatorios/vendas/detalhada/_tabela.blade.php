@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $moeda = fn ($v) => number_format((float) $v, 2, ',', '.');
    $data = fn ($v, $formato = 'd/m/Y H:i') => $v ? \Carbon\Carbon::parse($v)->format($formato) : '—';
    $colunas = 15;

    $tipoClasse = [
        'NFC-e' => 'bg-emerald-50 text-emerald-700',
        'NF-e'  => 'bg-blue-50 text-blue-700',
    ];

    $filtroPorProduto = ($filtros['tipo_busca'] ?? null) === 'produto' && ($filtros['busca'] ?? '') !== '';
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
                    <th class="px-4 py-3 text-center font-medium text-amber-50">Itens</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($documentos as $doc)
                    @php
                        $imposto = fn ($v) => $doc->tem_impostos ? $moeda($v) : '—';
                        $itensDoc = $itensPorDocumento[$doc->tipo . '-' . $doc->documento_id] ?? collect();
                        $chave = \Illuminate\Support\Str::slug($doc->tipo . ' ' . $doc->documento_id);
                        $titulo = $doc->tipo . ' ' . ($doc->numero ?? '—') . (($doc->serie !== null && $doc->serie !== '') ? ' / ' . $doc->serie : '');
                    @endphp
                    <tr class="hover:bg-gray-200 transition">
                        <td class="px-4 py-3 text-gray-600">{{ $data($doc->data) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $tipoClasse[$doc->tipo] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $doc->tipo }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-800">
                            {{ $doc->numero ?? '—' }}@if ($doc->serie !== null && $doc->serie !== '')<span class="text-xs font-normal text-gray-400"> / {{ $doc->serie }}</span>@endif
                        </td>
                        <td class="px-4 py-3 text-right text-gray-700">{{ $moeda($doc->valor_bruto) }}</td>
                        <td class="px-4 py-3 text-right {{ (float) $doc->desconto > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ $moeda($doc->desconto) }}</td>
                        <td class="px-4 py-3 text-right {{ (float) $doc->acrescimo > 0 ? 'text-gray-700' : 'text-gray-400' }}">{{ $moeda($doc->acrescimo) }}</td>
                        <td class="px-4 py-3 text-right font-medium text-gray-800">{{ $moeda($doc->valor_liquido) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ $imposto($doc->icms) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ $imposto($doc->pis) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ $imposto($doc->cofins) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ $imposto($doc->ipi) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ $imposto($doc->ibs) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ $imposto($doc->cbs) }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $doc->vendedor ?: '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <button type="button" onclick="abrirItens(this)"
                                    data-nota="{{ $chave }}"
                                    data-titulo="{{ $titulo }}"
                                    data-subtitulo="{{ $doc->vendedor ? 'Vendedor: ' . $doc->vendedor : '' }}"
                                    class="inline-flex items-center gap-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                </svg>
                                Ver itens
                                <span class="bg-gray-100 text-gray-600 rounded-full px-1.5 py-0.5 leading-none">{{ $doc->qtd_itens }}</span>
                            </button>

                            {{-- Conteúdo do modal desta venda: não aparece na tela, só quando o botão é clicado --}}
                            <template id="itens-nota-{{ $chave }}">
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4 text-left">
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Documento</p>
                                        <p class="text-sm font-medium text-gray-800">{{ $doc->tipo }} {{ $doc->numero ?? '—' }}</p>
                                        @if ($doc->serie !== null && $doc->serie !== '')
                                            <p class="text-xs text-gray-500">Série {{ $doc->serie }}</p>
                                        @endif
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Emissão</p>
                                        <p class="text-sm font-medium text-gray-800">{{ $data($doc->data) }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Vendedor</p>
                                        <p class="text-sm font-medium text-gray-800 truncate" title="{{ $doc->vendedor }}">{{ $doc->vendedor ?: '—' }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Valor líquido</p>
                                        <p class="text-sm font-medium text-gray-800">R$ {{ $moeda($doc->valor_liquido) }}</p>
                                        <p class="text-xs text-gray-500">{{ $doc->qtd_itens }} {{ $doc->qtd_itens == 1 ? 'item' : 'itens' }}</p>
                                    </div>
                                </div>

                                <div class="border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm whitespace-nowrap">
                                            <thead class="bg-gray-700 text-xs uppercase tracking-wide">
                                                <tr>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">#</th>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">Código</th>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">Descrição</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Qtd</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Valor bruto</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Desc.</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Acrésc.</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Valor líquido</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">ICMS</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">PIS</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">COFINS</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">IPI</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">IBS</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">CBS</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                @forelse ($itensDoc as $indice => $item)
                                                    @php $impostoItem = fn ($v) => $item->tem_impostos ? $moeda($v) : '—'; @endphp
                                                    <tr class="text-gray-700">
                                                        <td class="px-3 py-2.5 text-gray-400">{{ $indice + 1 }}</td>
                                                        <td class="px-3 py-2.5 font-mono text-xs">{{ $item->codigo ?: '—' }}</td>
                                                        <td class="px-3 py-2.5 font-medium">{{ $item->descricao ?: '—' }}</td>
                                                        <td class="px-3 py-2.5 text-right">{{ $qtd($item->quantidade) }}</td>
                                                        <td class="px-3 py-2.5 text-right">{{ $moeda($item->valor_bruto) }}</td>
                                                        <td class="px-3 py-2.5 text-right {{ (float) $item->desconto > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ $moeda($item->desconto) }}</td>
                                                        <td class="px-3 py-2.5 text-right {{ (float) $item->acrescimo > 0 ? '' : 'text-gray-400' }}">{{ $moeda($item->acrescimo) }}</td>
                                                        <td class="px-3 py-2.5 text-right font-medium">{{ $moeda($item->valor_liquido) }}</td>
                                                        <td class="px-3 py-2.5 text-right text-gray-600">{{ $impostoItem($item->icms) }}</td>
                                                        <td class="px-3 py-2.5 text-right text-gray-600">{{ $impostoItem($item->pis) }}</td>
                                                        <td class="px-3 py-2.5 text-right text-gray-600">{{ $impostoItem($item->cofins) }}</td>
                                                        <td class="px-3 py-2.5 text-right text-gray-600">{{ $impostoItem($item->ipi) }}</td>
                                                        <td class="px-3 py-2.5 text-right text-gray-600">{{ $impostoItem($item->ibs) }}</td>
                                                        <td class="px-3 py-2.5 text-right text-gray-600">{{ $impostoItem($item->cbs) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="14" class="px-3 py-6 text-center text-gray-400">Venda sem itens.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <p class="mt-3 text-xs text-gray-400 text-right">
                                    Impostos conforme gravados na emissão.
                                    @if ($filtroPorProduto) Mostrando só os itens que atendem à busca por produto. @endif
                                </p>
                            </template>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $colunas }}" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhuma venda emitida encontrada.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($documentos->count() > 0)
                <tfoot class="bg-gray-50 border-t border-gray-200 text-sm font-semibold text-gray-800">
                    <tr>
                        <td class="px-4 py-3" colspan="3">Total do filtro</td>
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
                        <td class="px-4 py-3" colspan="2"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

<p class="mt-3 text-xs text-gray-400">
    Valor bruto inclui os acréscimos (frete e outras despesas); valor líquido = valor bruto − descontos.
</p>

<div class="mt-4">
    {{ $documentos->links() }}
</div>