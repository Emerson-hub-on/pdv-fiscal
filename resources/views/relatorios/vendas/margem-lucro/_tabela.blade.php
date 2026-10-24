@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $moeda = fn ($v) => number_format((float) $v, 2, ',', '.');
    $perc = fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', '.') . '%';
    $data = fn ($v, $formato = 'd/m/Y H:i') => $v ? \Carbon\Carbon::parse($v)->format($formato) : '—';
    $colunas = 11;

    $tipoClasse = [
        'NFC-e' => 'bg-emerald-50 text-emerald-700',
        'NF-e'  => 'bg-blue-50 text-blue-700',
    ];

    // Verde com lucro, vermelho com prejuízo
    $classeLucro = fn ($v) => (float) $v < 0 ? 'text-red-600' : 'text-emerald-700';

    $filtroPorProduto = ($filtros['tipo_busca'] ?? null) === 'produto' && ($filtros['busca'] ?? '') !== '';
@endphp

<div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Venda (líquida)</p>
        <p class="text-lg font-semibold text-gray-800">R$ {{ $moeda($totais->valor_venda) }}</p>
        <p class="text-xs text-gray-400">{{ number_format($totais->documentos, 0, ',', '.') }} {{ $totais->documentos == 1 ? 'documento' : 'documentos' }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Custo</p>
        <p class="text-lg font-semibold text-gray-800">R$ {{ $moeda($totais->custo) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Carga tributária</p>
        <p class="text-lg font-semibold text-amber-600">R$ {{ $moeda($totais->carga) }}</p>
        <p class="text-xs text-gray-400">{{ $perc($totais->carga_percentual) }} da venda</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Lucro</p>
        <p class="text-lg font-semibold {{ $classeLucro($totais->lucro) }}">R$ {{ $moeda($totais->lucro) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Margem</p>
        <p class="text-lg font-semibold {{ $classeLucro($totais->lucro) }}">{{ $perc($totais->margem) }}</p>
    </div>
</div>

@if ($totais->itens_sem_dados > 0)
    <p class="mb-4 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
        {{ number_format($totais->itens_sem_dados, 0, ',', '.') }} {{ $totais->itens_sem_dados == 1 ? 'item' : 'itens' }}
        sem custo ou sem impostos gravados ficam fora dos valores acima e aparecem com “—”
        ({{ number_format($totais->itens_sem_custo, 0, ',', '.') }} sem custo no cadastro do produto,
        {{ number_format($totais->itens_sem_impostos, 0, ',', '.') }} de notas emitidas antes do registro dos impostos).
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
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Venda</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Custo</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Carga trib.</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Lucro</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Margem</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Vendedor</th>
                    <th class="px-4 py-3 text-center font-medium text-amber-50">Itens</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($documentos as $doc)
                    @php
                        $completo = (bool) $doc->completo;
                        $itensDoc = $itensPorDocumento[$doc->tipo . '-' . $doc->documento_id] ?? collect();
                        $chave = \Illuminate\Support\Str::slug($doc->tipo . ' ' . $doc->documento_id);
                        $titulo = $doc->tipo . ' ' . ($doc->numero ?? '—') . (($doc->serie !== null && $doc->serie !== '') ? ' / ' . $doc->serie : '');
                        $motivoVazio = 'Venda com item sem custo ou sem impostos gravados';
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
                        <td class="px-4 py-3 text-right text-gray-800 font-medium">{{ $moeda($doc->valor_venda) }}</td>
                        <td class="px-4 py-3 text-right text-gray-700" @unless ($completo) title="{{ $motivoVazio }}" @endunless>{{ $completo ? $moeda($doc->custo) : '—' }}</td>
                        <td class="px-4 py-3 text-right text-gray-700" @unless ($completo) title="{{ $motivoVazio }}" @endunless>{{ $completo ? $moeda($doc->carga) : '—' }}</td>
                        <td class="px-4 py-3 text-right font-medium {{ $completo ? $classeLucro($doc->lucro) : 'text-gray-400' }}" @unless ($completo) title="{{ $motivoVazio }}" @endunless>{{ $completo ? $moeda($doc->lucro) : '—' }}</td>
                        <td class="px-4 py-3 text-right font-medium {{ $completo ? $classeLucro($doc->lucro) : 'text-gray-400' }}" @unless ($completo) title="{{ $motivoVazio }}" @endunless>{{ $completo ? $perc($doc->margem) : '—' }}</td>
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
                                        <p class="text-xs text-gray-500">{{ $data($doc->data) }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Venda</p>
                                        <p class="text-sm font-medium text-gray-800">R$ {{ $moeda($doc->valor_venda) }}</p>
                                        <p class="text-xs text-gray-500">{{ $doc->qtd_itens }} {{ $doc->qtd_itens == 1 ? 'item' : 'itens' }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Custo + carga trib.</p>
                                        <p class="text-sm font-medium text-gray-800">{{ $completo ? 'R$ ' . $moeda($doc->custo + $doc->carga) : '—' }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Lucro / margem</p>
                                        <p class="text-sm font-medium {{ $completo ? $classeLucro($doc->lucro) : 'text-gray-800' }}">{{ $completo ? 'R$ ' . $moeda($doc->lucro) : '—' }}</p>
                                        <p class="text-xs text-gray-500">{{ $completo ? $perc($doc->margem) : '' }}</p>
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
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Custo unit.</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Venda</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Custo</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">ICMS</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">PIS</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">COFINS</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Carga trib.</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Lucro</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Margem</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                @forelse ($itensDoc as $indice => $item)
                                                    @php
                                                        $itemCompleto = (bool) $item->completo;
                                                        $semCusto = ! $item->tem_custo;
                                                        $semImposto = ! $item->tem_impostos;
                                                        $motivoItem = $semCusto ? 'Produto sem preço de custo no momento da venda' : 'Nota emitida antes do registro dos impostos';
                                                    @endphp
                                                    <tr class="text-gray-700">
                                                        <td class="px-3 py-2.5 text-gray-400">{{ $indice + 1 }}</td>
                                                        <td class="px-3 py-2.5 font-mono text-xs">{{ $item->codigo ?: '—' }}</td>
                                                        <td class="px-3 py-2.5 font-medium">{{ $item->descricao ?: '—' }}</td>
                                                        <td class="px-3 py-2.5 text-right">{{ $qtd($item->quantidade) }}</td>
                                                        <td class="px-3 py-2.5 text-right">{{ $item->custo_unitario !== null ? number_format((float) $item->custo_unitario, 4, ',', '.') : '—' }}</td>
                                                        <td class="px-3 py-2.5 text-right font-medium">{{ $moeda($item->valor_venda) }}</td>
                                                        <td class="px-3 py-2.5 text-right">{{ $item->tem_custo ? $moeda($item->custo) : '—' }}</td>
                                                        <td class="px-3 py-2.5 text-right text-gray-600">{{ $item->tem_impostos ? $moeda($item->icms) : '—' }}</td>
                                                        <td class="px-3 py-2.5 text-right text-gray-600">{{ $item->tem_impostos ? $moeda($item->pis) : '—' }}</td>
                                                        <td class="px-3 py-2.5 text-right text-gray-600">{{ $item->tem_impostos ? $moeda($item->cofins) : '—' }}</td>
                                                        <td class="px-3 py-2.5 text-right">{{ $item->tem_impostos ? $moeda($item->carga) : '—' }}</td>
                                                        <td class="px-3 py-2.5 text-right font-medium {{ $itemCompleto ? $classeLucro($item->lucro) : 'text-gray-400' }}" @unless ($itemCompleto) title="{{ $motivoItem }}" @endunless>{{ $itemCompleto ? $moeda($item->lucro) : '—' }}</td>
                                                        <td class="px-3 py-2.5 text-right font-medium {{ $itemCompleto ? $classeLucro($item->lucro) : 'text-gray-400' }}" @unless ($itemCompleto) title="{{ $motivoItem }}" @endunless>{{ $itemCompleto ? $perc($item->margem) : '—' }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="13" class="px-3 py-6 text-center text-gray-400">Venda sem itens.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <p class="mt-3 text-xs text-gray-400 text-right">
                                    Custo e impostos conforme gravados na emissão. Lucro = venda − custo − carga tributária.
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
                        <td class="px-4 py-3 text-right">{{ $moeda($totais->valor_venda) }}</td>
                        <td class="px-4 py-3 text-right">{{ $moeda($totais->custo) }}</td>
                        <td class="px-4 py-3 text-right">{{ $moeda($totais->carga) }}</td>
                        <td class="px-4 py-3 text-right {{ $classeLucro($totais->lucro) }}">{{ $moeda($totais->lucro) }}</td>
                        <td class="px-4 py-3 text-right {{ $classeLucro($totais->lucro) }}">{{ $perc($totais->margem) }}</td>
                        <td class="px-4 py-3" colspan="2"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

<p class="mt-3 text-xs text-gray-400">
    Venda = valor líquido (produtos + acréscimos − descontos). Carga tributária = ICMS + PIS + COFINS{{ $totais->incluiu_ibs_cbs ? ' + IBS + CBS' : '' }} destacados na nota; o IPI não entra, pois é cobrado por fora da venda.
    Lucro = venda − custo − carga tributária; margem = lucro ÷ venda.
</p>

<div class="mt-4">
    {{ $documentos->links() }}
</div>
