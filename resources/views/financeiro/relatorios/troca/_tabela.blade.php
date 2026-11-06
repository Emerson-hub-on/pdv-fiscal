@php
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $data = fn ($v, $formato = 'd/m/Y') => $v ? \Carbon\Carbon::parse($v)->format($formato) : '—';
    $qtd = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');

    $situacoes = [
        'emitida'   => ['label' => 'Autorizada', 'classe' => 'bg-green-50 text-green-700', 'ponto' => 'bg-green-500'],
        'rascunho'  => ['label' => 'Rascunho', 'classe' => 'bg-amber-50 text-amber-700', 'ponto' => 'bg-amber-500'],
        'cancelada' => ['label' => 'Cancelada', 'classe' => 'bg-red-50 text-red-600', 'ponto' => 'bg-red-500'],
    ];

    $colunas = 11;
@endphp

<div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Trocas</p>
        <p class="text-lg font-semibold text-gray-800">{{ number_format($totais->quantidade, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Valor devolvido</p>
        <p class="text-lg font-semibold text-gray-800">{{ $dinheiro($totais->valor) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Crédito gerado</p>
        <p class="text-lg font-semibold text-[#10284A]">{{ $dinheiro($totais->credito_gerado) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Crédito usado</p>
        <p class="text-lg font-semibold text-green-700">{{ $dinheiro($totais->credito_usado) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Crédito em aberto</p>
        <p class="text-lg font-semibold text-amber-600">{{ $dinheiro($totais->credito_aberto) }}</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead class="bg-[#10284A] rounded-lg font-medium text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Data</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">NF-e de entrada</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Situação</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Venda original</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Cliente</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">CFOP</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Valor devolvido</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Vale-troca</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Saldo do crédito</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Saída do item novo</th>
                    <th class="px-4 py-3 text-center font-medium text-amber-50">Itens</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($trocas as $troca)
                    @php
                        $situacao = $situacoes[$troca->status] ?? $situacoes['rascunho'];
                        $o = $troca->rel_original;
                        $credito = $troca->rel_credito;
                        $rotuloEntrada = $troca->numero ? "NF-e nº {$troca->numero}" : "Rascunho #{$troca->id}";
                    @endphp
                    <tr class="hover:bg-gray-200 transition">
                        <td class="px-4 py-3 text-gray-600">{{ $data($troca->emitida_em ?? $troca->created_at, 'd/m/Y H:i') }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800">
                            <a href="{{ $troca->status === 'rascunho' ? route('notasfiscais.edit', $troca) : route('notasfiscais.show', $troca) }}"
                               class="hover:text-blue-600 hover:underline">{{ $rotuloEntrada }}</a>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $situacao['classe'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $situacao['ponto'] }}"></span>
                                {{ $situacao['label'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-700">
                            <span class="inline-block rounded px-1.5 py-0.5 text-[11px] font-semibold {{ $o['tipo'] === 'NFC-e' ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700' }}">{{ $o['tipo'] }}</span>
                            nº {{ $o['numero'] }} <span class="text-gray-400">/ {{ $o['serie'] }} · {{ $data($o['data']) }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-800 max-w-xs truncate" title="{{ $troca->cliente->nome ?? '' }}">
                            {{ $troca->cliente->nome ?? '—' }}
                            <span class="block text-xs text-gray-400 font-mono">{{ $troca->cliente->cpf_cnpj_formatado ?? '' }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 font-mono">{{ $troca->cfopSaida->codigo ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-800">{{ $dinheiro($troca->valor_total) }}</td>
                        <td class="px-4 py-3 font-mono text-gray-700">{{ $credito->codigo ?? '—' }}</td>
                        <td class="px-4 py-3 text-right {{ $credito && (float) $credito->saldo > 0 ? 'font-semibold text-amber-600' : 'text-gray-400' }}">
                            {{ $credito ? $dinheiro($credito->saldo) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $troca->rel_saidas->implode(', ') ?: '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <button type="button" onclick="abrirItens(this)"
                                    data-nota="{{ $troca->id }}"
                                    data-titulo="Troca · {{ $rotuloEntrada }}"
                                    data-subtitulo="{{ $o['tipo'] }} nº {{ $o['numero'] }} — {{ $troca->cliente->nome ?? '' }}"
                                    class="inline-flex items-center gap-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                </svg>
                                Ver itens
                                <span class="bg-gray-100 text-gray-600 rounded-full px-1.5 py-0.5 leading-none">{{ $troca->itens->count() }}</span>
                            </button>

                            {{-- Conteúdo do modal desta troca: só aparece quando o botão é clicado --}}
                            <template id="itens-nota-{{ $troca->id }}">
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4 text-left">
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Venda original</p>
                                        <p class="text-sm font-medium text-gray-800">{{ $o['tipo'] }} nº {{ $o['numero'] }} / {{ $o['serie'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $data($o['data']) }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Cliente</p>
                                        <p class="text-sm font-medium text-gray-800 truncate">{{ $troca->cliente->nome ?? '—' }}</p>
                                        <p class="text-xs text-gray-500 font-mono">{{ $troca->cliente->cpf_cnpj_formatado ?? '' }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Crédito em loja</p>
                                        <p class="text-sm font-medium text-gray-800">{{ $credito->codigo ?? 'Ainda não gerado' }}</p>
                                        <p class="text-xs text-gray-500">{{ $credito ? 'usado ' . $dinheiro($troca->rel_credito_usado) . ' · saldo ' . $dinheiro($credito->saldo) : 'gerado na autorização' }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Saída do item novo</p>
                                        <p class="text-sm font-medium text-gray-800">{{ $troca->rel_saidas->implode(', ') ?: '—' }}</p>
                                    </div>
                                </div>

                                <div class="border border-gray-200 rounded-lg overflow-hidden">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                            <tr>
                                                <th class="px-3 py-2 text-left font-medium">Item da venda</th>
                                                <th class="px-3 py-2 text-left font-medium">Produto</th>
                                                <th class="px-3 py-2 text-right font-medium">Qtd. devolvida</th>
                                                <th class="px-3 py-2 text-right font-medium">Valor unit.</th>
                                                <th class="px-3 py-2 text-right font-medium">Valor</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            @forelse ($troca->itens as $item)
                                                <tr>
                                                    <td class="px-3 py-2 text-left font-mono text-gray-500">{{ $item->ref_nitem ?: '—' }}</td>
                                                    <td class="px-3 py-2 text-left text-gray-800">{{ $item->descricao ?: ($item->produto->nome ?? '—') }}</td>
                                                    <td class="px-3 py-2 text-right">{{ $qtd($item->quantidade) }} {{ $item->produto->unidade_comercial ?? '' }}</td>
                                                    <td class="px-3 py-2 text-right">{{ $dinheiro($item->valor_unitario) }}</td>
                                                    <td class="px-3 py-2 text-right font-medium">{{ $dinheiro($item->valor_total) }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="5" class="px-3 py-6 text-center text-gray-400">Troca sem itens.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <div class="flex flex-wrap justify-end gap-x-6 gap-y-1 mt-4 text-sm text-gray-600">
                                    <span>Valor devolvido: <strong class="text-gray-800">{{ $dinheiro($troca->valor_total) }}</strong></span>
                                </div>
                            </template>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $colunas }}" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhuma troca encontrada.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    {{ $trocas->links() }}
</div>
