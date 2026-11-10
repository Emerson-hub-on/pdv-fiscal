@php
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $data = fn ($v, $formato = 'd/m/Y H:i') => $v ? \Carbon\Carbon::parse($v)->format($formato) : '—';

    $situacoes = [
        'concluido' => ['classe' => 'bg-green-50 text-green-700', 'ponto' => 'bg-green-500', 'texto' => 'text-green-700'],
        'andamento' => ['classe' => 'bg-amber-50 text-amber-700', 'ponto' => 'bg-amber-500', 'texto' => 'text-amber-700'],
        'cancelado' => ['classe' => 'bg-red-50 text-red-600', 'ponto' => 'bg-red-500', 'texto' => 'text-red-600'],
    ];
    $origens = [
        'mesa'  => ['rotulo' => 'Mesa', 'classe' => 'bg-slate-100 text-slate-700'],
        'ifood' => ['rotulo' => 'iFood', 'classe' => 'bg-red-600 text-white'],
    ];
    $nfces = [
        'emitida'      => 'text-green-700',
        'contingencia' => 'text-amber-700',
        'pendente'     => 'text-amber-700',
        'aguardando'   => 'text-blue-700',
        'cancelada'    => 'text-red-600',
        'sem'          => 'text-gray-400',
    ];

    $colunas = 10;
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Vendido (concluídos)</p>
        <p class="text-lg font-semibold text-green-700">{{ $dinheiro($totais->valor_vendido) }}</p>
        <p class="text-xs text-gray-400">{{ number_format($totais->concluidos, 0, ',', '.') }} {{ $totais->concluidos === 1 ? 'pedido' : 'pedidos' }} · {{ number_format($totais->andamento, 0, ',', '.') }} em andamento</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Mesas</p>
        <p class="text-lg font-semibold text-gray-800">{{ $dinheiro($totais->valor_mesas) }}</p>
        <p class="text-xs text-gray-400">{{ number_format($totais->mesas, 0, ',', '.') }} {{ $totais->mesas === 1 ? 'conta paga' : 'contas pagas' }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">iFood</p>
        <p class="text-lg font-semibold text-gray-800">{{ $dinheiro($totais->valor_ifood) }}</p>
        <p class="text-xs text-gray-400">{{ number_format($totais->ifood, 0, ',', '.') }} {{ $totais->ifood === 1 ? 'pedido concluído' : 'pedidos concluídos' }} · só os itens</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Cancelados</p>
        <p class="text-lg font-semibold text-red-600">{{ number_format($totais->cancelados, 0, ',', '.') }}</p>
        <p class="text-xs text-gray-400">{{ $dinheiro($totais->valor_cancelado) }}</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead class="bg-[#10284A] rounded-lg font-medium text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Origem</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Pedido</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Data</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Cliente</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Garçom / Entrega</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Pagamento</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">NFC-e</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Total</th>
                    <th class="px-4 py-3 text-center font-medium text-amber-50">Itens</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($pedidos as $pedido)
                    @php
                        $cancelado = $pedido['status'] === 'cancelado';
                        $situacao = $situacoes[$pedido['status']];
                        $origem = $origens[$pedido['origem']];
                        $qtdItens = array_sum(array_column($pedido['itens'], 'quantidade'));
                    @endphp
                    <tr class="hover:bg-gray-200 transition">
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold {{ $origem['classe'] }}">{{ $origem['rotulo'] }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-800">{{ $pedido['pedido'] }}</p>
                            @if ($pedido['detalhe'])
                                <p class="text-xs text-gray-500">{{ $pedido['detalhe'] }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $data($pedido['momento']) }}</td>
                        <td class="px-4 py-3 text-gray-800 max-w-xs truncate" title="{{ $pedido['cliente'] }}">{{ $pedido['cliente'] ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 max-w-48 truncate" title="{{ $pedido['atendimento'] }}">{{ $pedido['atendimento'] ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 max-w-xs truncate" title="{{ $pedido['pagamento'] }}">{{ $pedido['pagamento'] ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap {{ $situacao['classe'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $situacao['ponto'] }}"></span>
                                {{ $pedido['status_rotulo'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs font-medium {{ $nfces[$pedido['nfce_situacao']] ?? 'text-gray-500' }}">{{ $pedido['nfce'] }}</td>
                        <td class="px-4 py-3 text-right font-semibold {{ $cancelado ? 'text-red-600 line-through' : 'text-gray-800' }}">{{ $dinheiro($pedido['total']) }}</td>
                        <td class="px-4 py-3 text-center">
                            <button type="button" onclick="abrirItens(this)"
                                    data-pedido="{{ $pedido['chave'] }}"
                                    data-titulo="{{ $pedido['pedido'] }}"
                                    data-subtitulo="{{ $origem['rotulo'] }} · {{ $data($pedido['momento']) }}{{ $pedido['cliente'] ? ' · ' . $pedido['cliente'] : '' }}"
                                    class="inline-flex items-center gap-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                </svg>
                                Ver itens
                                <span class="bg-gray-100 text-gray-600 rounded-full px-1.5 py-0.5 leading-none">{{ $qtdItens }}</span>
                            </button>

                            {{-- Conteúdo do modal deste pedido: não aparece na tela, só quando o botão é clicado --}}
                            <template id="itens-pedido-{{ $pedido['chave'] }}">
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4 text-left">
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Cliente</p>
                                        <p class="text-sm font-medium text-gray-800 truncate" title="{{ $pedido['cliente'] }}">{{ $pedido['cliente'] ?: '—' }}</p>
                                        <p class="text-xs text-gray-500 truncate">{{ $pedido['atendimento'] }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">{{ $pedido['origem'] === 'mesa' ? 'Conta' : 'Pedido' }}</p>
                                        <p class="text-sm font-medium text-gray-800">{{ $data($pedido['momento']) }}</p>
                                        <p class="text-xs text-gray-500">{{ $pedido['origem'] === 'mesa' ? 'Aberta em ' . $data($pedido['aberto_em']) : $pedido['detalhe'] }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Situação</p>
                                        <p class="text-sm font-medium {{ $situacao['texto'] }}">{{ $pedido['status_rotulo'] }}</p>
                                        <p class="text-xs {{ $nfces[$pedido['nfce_situacao']] ?? 'text-gray-500' }}">NFC-e: {{ $pedido['nfce'] }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Total</p>
                                        <p class="text-sm font-semibold {{ $cancelado ? 'text-red-600 line-through' : 'text-gray-800' }}">{{ $dinheiro($pedido['total']) }}</p>
                                        @if ($pedido['origem'] === 'ifood')
                                            <p class="text-xs text-gray-500">Entrega {{ $dinheiro($pedido['taxa_entrega']) }} · Desc. {{ $dinheiro($pedido['descontos']) }}</p>
                                        @elseif ($pedido['troco'] > 0)
                                            <p class="text-xs text-gray-500">Troco {{ $dinheiro($pedido['troco']) }}</p>
                                        @endif
                                    </div>
                                </div>

                                @if ($pedido['pagamentos'])
                                    <div class="mb-4 flex flex-wrap gap-2 text-left">
                                        @foreach ($pedido['pagamentos'] as $pagamento)
                                            <span class="text-xs text-gray-700 bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5">
                                                {{ $pagamento['descricao'] }}: <strong>{{ $dinheiro($pagamento['valor']) }}</strong>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($pedido['observacao'])
                                    <p class="mb-4 text-xs text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-left whitespace-normal">
                                        Observação: {{ $pedido['observacao'] }}
                                    </p>
                                @endif

                                <div class="border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm whitespace-nowrap">
                                            <thead class="bg-[#10284A] text-xs uppercase tracking-wide">
                                                <tr>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">#</th>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">Descrição</th>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">Observação</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Quantidade</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Valor unit.</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                @forelse ($pedido['itens'] as $indice => $item)
                                                    <tr class="{{ $cancelado ? 'text-gray-400' : 'text-gray-700' }}">
                                                        <td class="px-3 py-2.5 text-gray-400">{{ $indice + 1 }}</td>
                                                        <td class="px-3 py-2.5 font-medium">{{ $item['nome'] }}</td>
                                                        <td class="px-3 py-2.5 text-xs text-gray-500 max-w-xs truncate" title="{{ $item['observacao'] }}">{{ $item['observacao'] ?: '—' }}</td>
                                                        <td class="px-3 py-2.5 text-right">{{ $item['quantidade'] }}</td>
                                                        <td class="px-3 py-2.5 text-right">{{ $dinheiro($item['preco_unitario']) }}</td>
                                                        <td class="px-3 py-2.5 text-right font-medium">{{ $dinheiro($item['subtotal']) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="6" class="px-3 py-6 text-center text-gray-400">Pedido sem itens.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="flex flex-wrap justify-end gap-x-6 gap-y-1 mt-4 text-sm text-gray-600">
                                    <span>Itens: <strong class="text-gray-800">{{ $qtdItens }}</strong></span>
                                    <span>Total: <strong class="{{ $cancelado ? 'text-red-600' : 'text-gray-800' }}">{{ $dinheiro($pedido['total']) }}</strong></span>
                                </div>
                            </template>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $colunas }}" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhum pedido encontrado no período.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    {{ $pedidos->links() }}
</div>
