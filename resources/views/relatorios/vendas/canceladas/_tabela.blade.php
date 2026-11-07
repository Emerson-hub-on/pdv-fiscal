@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $moeda = fn ($v) => number_format((float) $v, 2, ',', '.');
    $data = fn ($v, $formato = 'd/m/Y H:i') => $v ? \Carbon\Carbon::parse($v)->format($formato) : '—';
    $colunas = 11;
    // Quem liberou o cancelamento: o supervisor, ou o próprio operador quando ele tem liberação para cancelar
    $liberadoPor = fn ($doc) => $doc->autorizado_por === 'Operador liberado' ? 'Próprio operador (liberado)' : $doc->autorizado_por;

    $tipoClasse = [
        'NFC-e' => 'bg-emerald-50 text-emerald-700',
        'NF-e'  => 'bg-blue-50 text-blue-700',
    ];
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Vendas canceladas</p>
        <p class="text-lg font-semibold text-gray-800">{{ number_format($totais->documentos, 0, ',', '.') }}</p>
        <p class="text-xs text-gray-400">{{ number_format($totais->itens, 0, ',', '.') }} {{ $totais->itens == 1 ? 'item' : 'itens' }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Valor cancelado</p>
        <p class="text-lg font-semibold text-red-600">R$ {{ $moeda($totais->valor) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">NFC-e</p>
        <p class="text-lg font-semibold text-gray-800">R$ {{ $moeda($totais->valor_nfce) }}</p>
        <p class="text-xs text-gray-400">{{ number_format($totais->qtd_nfce, 0, ',', '.') }} {{ $totais->qtd_nfce == 1 ? 'cupom' : 'cupons' }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">NF-e</p>
        <p class="text-lg font-semibold text-gray-800">R$ {{ $moeda($totais->valor_nfe) }}</p>
        <p class="text-xs text-gray-400">{{ number_format($totais->qtd_nfe, 0, ',', '.') }} {{ $totais->qtd_nfe == 1 ? 'nota' : 'notas' }}</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead class="bg-[#10284A] rounded-lg font-medium text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Cancelada em</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Tipo</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Nº doc.</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Venda em</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Cliente</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Valor</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Motivo</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Cancelado por</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Liberado por</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Vendedor</th>
                    <th class="px-4 py-3 text-center font-medium text-amber-50">Itens</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($documentos as $doc)
                    @php
                        $itensDoc = $itensPorDocumento[$doc->tipo . '-' . $doc->documento_id] ?? collect();
                        $chave = \Illuminate\Support\Str::slug($doc->tipo . ' ' . $doc->documento_id);
                        $titulo = $doc->tipo . ' ' . ($doc->numero ?? '—') . (($doc->serie !== null && $doc->serie !== '') ? ' / ' . $doc->serie : '');
                    @endphp
                    <tr class="hover:bg-gray-200 transition">
                        <td class="px-4 py-3 text-gray-700 font-medium">{{ $data($doc->cancelado_em) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $tipoClasse[$doc->tipo] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $doc->tipo }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-800">
                            @if ($doc->tipo === 'NF-e')
                                <a href="{{ route('notasfiscais.show', $doc->documento_id) }}" class="hover:underline">{{ $doc->numero ?? '—' }}</a>
                            @else
                                {{ $doc->numero ?? '—' }}
                            @endif
                            @if ($doc->serie !== null && $doc->serie !== '')<span class="text-xs font-normal text-gray-400"> / {{ $doc->serie }}</span>@endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $data($doc->emitida_em) }}</td>
                        <td class="px-4 py-3 text-gray-700 max-w-56 truncate" title="{{ $doc->cliente }}">{{ $doc->cliente ?: 'Consumidor' }}</td>
                        <td class="px-4 py-3 text-right font-medium text-red-600">{{ $moeda($doc->valor) }}</td>
                        <td class="px-4 py-3 text-gray-600 max-w-72 truncate" title="{{ $doc->motivo }}">{{ $doc->motivo ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $doc->cancelado_por ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $liberadoPor($doc) ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $doc->vendedor ?: '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <button type="button" onclick="abrirItens(this)"
                                    data-nota="{{ $chave }}"
                                    data-titulo="{{ $titulo }}"
                                    data-subtitulo="{{ 'Cancelada em ' . $data($doc->cancelado_em) . ($doc->cliente ? ' · ' . $doc->cliente : '') }}"
                                    class="inline-flex items-center gap-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                </svg>
                                Ver itens
                                <span class="bg-gray-100 text-gray-600 rounded-full px-1.5 py-0.5 leading-none">{{ $doc->qtd_itens }}</span>
                            </button>

                            {{-- Conteúdo do modal desta venda: só aparece quando o botão é clicado --}}
                            <template id="itens-nota-{{ $chave }}">
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4 text-left whitespace-normal">
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Documento</p>
                                        <p class="text-sm font-medium text-gray-800">{{ $titulo }}</p>
                                        <p class="text-xs text-gray-500">Venda em {{ $data($doc->emitida_em) }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Cancelamento</p>
                                        <p class="text-sm font-medium text-gray-800">{{ $data($doc->cancelado_em) }}</p>
                                        @if ($doc->protocolo_cancelamento)
                                            <p class="text-xs text-gray-500">Protocolo {{ $doc->protocolo_cancelamento }}</p>
                                        @endif
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Cancelado por</p>
                                        <p class="text-sm font-medium text-gray-800">{{ $doc->cancelado_por ?: '—' }}</p>
                                        <p class="text-xs text-gray-500">Liberado por: {{ $liberadoPor($doc) ?: '—' }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Valor cancelado</p>
                                        <p class="text-sm font-medium text-red-600">R$ {{ $moeda($doc->valor) }}</p>
                                        <p class="text-xs text-gray-500">{{ $doc->qtd_itens }} {{ $doc->qtd_itens == 1 ? 'item' : 'itens' }}</p>
                                    </div>
                                    <div class="col-span-2 sm:col-span-4 bg-red-50 border border-red-100 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-red-700">Motivo</p>
                                        <p class="text-sm text-gray-800 break-words">{{ $doc->motivo ?: 'Não informado' }}</p>
                                    </div>
                                </div>

                                <div class="border border-gray-200 rounded-lg overflow-hidden">
                                    <table class="w-full text-sm whitespace-nowrap">
                                        <thead class="bg-[#10284A] text-xs uppercase tracking-wide">
                                            <tr>
                                                <th class="px-3 py-2.5 text-left font-medium text-amber-50">#</th>
                                                <th class="px-3 py-2.5 text-left font-medium text-amber-50">Código</th>
                                                <th class="px-3 py-2.5 text-left font-medium text-amber-50">Descrição</th>
                                                <th class="px-3 py-2.5 text-right font-medium text-amber-50">Qtd</th>
                                                <th class="px-3 py-2.5 text-right font-medium text-amber-50">Valor unit.</th>
                                                <th class="px-3 py-2.5 text-right font-medium text-amber-50">Desc.</th>
                                                <th class="px-3 py-2.5 text-right font-medium text-amber-50">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            @forelse ($itensDoc as $indice => $item)
                                                <tr class="text-gray-700">
                                                    <td class="px-3 py-2.5 text-gray-400">{{ $indice + 1 }}</td>
                                                    <td class="px-3 py-2.5 font-mono text-xs">{{ $item->codigo ?: '—' }}</td>
                                                    <td class="px-3 py-2.5 font-medium">{{ $item->descricao ?: '—' }}</td>
                                                    <td class="px-3 py-2.5 text-right">{{ $qtd($item->quantidade) }}</td>
                                                    <td class="px-3 py-2.5 text-right">{{ $moeda($item->valor_unitario) }}</td>
                                                    <td class="px-3 py-2.5 text-right {{ (float) $item->desconto > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ $moeda($item->desconto) }}</td>
                                                    <td class="px-3 py-2.5 text-right font-medium">{{ $moeda($item->valor_total) }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="7" class="px-3 py-6 text-center text-gray-400">Venda sem itens.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </template>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $colunas }}" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhuma venda cancelada encontrada no filtro.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($documentos->count() > 0)
                <tfoot class="bg-gray-50 border-t border-gray-200 text-sm font-semibold text-gray-800">
                    <tr>
                        <td class="px-4 py-3" colspan="5">Total do filtro</td>
                        <td class="px-4 py-3 text-right text-red-600">{{ $moeda($totais->valor) }}</td>
                        <td class="px-4 py-3" colspan="5"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

<p class="mt-3 text-xs text-gray-400">
    NFC-e: data, quem cancelou e quem liberou vêm do registro do caixa (o supervisor que autorizou, ou o próprio operador quando ele tem liberação para cancelar).
    NF-e: data e protocolo do cancelamento na SEFAZ. Só entram NF-e de venda (devolução, complementar e ajuste ficam de fora).
</p>

<div class="mt-4">
    {{ $documentos->links() }}
</div>
