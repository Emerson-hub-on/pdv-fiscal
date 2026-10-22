@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $data = fn ($v, $formato = 'd/m/Y') => $v ? \Carbon\Carbon::parse($v)->format($formato) : '—';
    $nomeCliente = fn ($c) => $c ? ($c->nome_exibicao ?? $c->nome ?? $c->razao_social ?? null) : null;

    $temRotaImprimir = \Illuminate\Support\Facades\Route::has('pedidos-venda.imprimir');
    // finalizado = pedido fechado; emitido = já emitido no caixa
    $statusPedido = [
        'finalizado' => ['label' => 'Finalizado', 'classe' => 'bg-green-50 text-green-700', 'ponto' => 'bg-green-500', 'texto' => 'text-green-700'],
        'emitido'    => ['label' => 'Emitido', 'classe' => 'bg-blue-50 text-blue-700', 'ponto' => 'bg-blue-500', 'texto' => 'text-blue-700'],
        'cancelado'  => ['label' => 'Cancelado', 'classe' => 'bg-red-50 text-red-600', 'ponto' => 'bg-red-500', 'texto' => 'text-red-600'],
    ];

    $colunas = 11;
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Finalizados</p>
        <p class="text-lg font-semibold text-green-700">{{ number_format($totais->finalizados, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Emitidos</p>
        <p class="text-lg font-semibold text-blue-700">{{ number_format($totais->emitidos, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Valor dos pedidos</p>
        <p class="text-lg font-semibold text-gray-800">{{ $dinheiro($totais->valor_pedidos) }}</p>
        <p class="text-xs text-gray-400">finalizados + emitidos</p>
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
            <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Pedido</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Data</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Previsão de entrega</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Cliente</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">CPF/CNPJ</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Operador</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Forma de pagamento</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Observação</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Total</th>
                    <th class="px-4 py-3 text-center font-medium text-amber-50">Itens</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($pedidos as $pedido)
                    @php
                        $cancelado = $pedido->status === 'cancelado';
                        $situacao = $statusPedido[$pedido->status] ?? ['label' => ucfirst($pedido->status), 'classe' => 'bg-gray-100 text-gray-700', 'ponto' => 'bg-gray-400', 'texto' => 'text-gray-700'];
                        $cliente = $nomeCliente($pedido->cliente);
                        $documento = $pedido->cliente->cpf_cnpj_formatado ?? $pedido->cliente->cpf_cnpj ?? null;
                    @endphp
                    <tr class="hover:bg-gray-200 transition">
                        <td class="px-4 py-3 font-medium text-gray-800">
                            @if ($temRotaImprimir)
                                <a href="{{ route('pedidos-venda.imprimir', $pedido) }}" target="_blank" class="hover:text-blue-600 hover:underline">{{ $pedido->numeroExibicao() }}</a>
                            @else
                                {{ $pedido->numeroExibicao() }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $data($pedido->data_pedido) }}</td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $data($pedido->previsao_entrega) }}</td>
                        <td class="px-4 py-3 text-gray-800 max-w-xs truncate" title="{{ $cliente }}">{{ $cliente ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $documento ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $pedido->operador->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $pedido->formaPagamento->descricao ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap {{ $situacao['classe'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $situacao['ponto'] }}"></span>
                                {{ $situacao['label'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500 max-w-xs truncate" title="{{ $pedido->descricao }}">{{ $pedido->descricao ?: '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold whitespace-nowrap {{ $cancelado ? 'text-red-600 line-through' : 'text-gray-800' }}">{{ $dinheiro($pedido->valor_total) }}</td>
                        <td class="px-4 py-3 text-center">
                            <button type="button" onclick="abrirItens(this)"
                                    data-nota="{{ $pedido->id }}"
                                    data-titulo="Pedido de venda {{ $pedido->numeroExibicao() }}"
                                    data-subtitulo="{{ $cliente ?? '' }}"
                                    class="inline-flex items-center gap-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                </svg>
                                Ver itens
                                <span class="bg-gray-100 text-gray-600 rounded-full px-1.5 py-0.5 leading-none">{{ $pedido->itens->count() }}</span>
                            </button>

                            {{-- Conteúdo do modal deste pedido: não aparece na tela, só quando o botão é clicado --}}
                            <template id="itens-nota-{{ $pedido->id }}">
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4 text-left">
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Cliente</p>
                                        <p class="text-sm font-medium text-gray-800 truncate" title="{{ $cliente }}">{{ $cliente ?: '—' }}</p>
                                        <p class="text-xs text-gray-500 font-mono">{{ $documento }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Pedido</p>
                                        <p class="text-sm font-medium text-gray-800">{{ $data($pedido->data_pedido) }}</p>
                                        <p class="text-xs text-gray-500">Entrega: {{ $data($pedido->previsao_entrega) }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Situação</p>
                                        <p class="text-sm font-medium {{ $situacao['texto'] }}">{{ $situacao['label'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $pedido->operador->name ?? '—' }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Total do pedido</p>
                                        <p class="text-sm font-semibold {{ $cancelado ? 'text-red-600 line-through' : 'text-gray-800' }}">{{ $dinheiro($pedido->valor_total) }}</p>
                                        <p class="text-xs text-gray-500">{{ $pedido->formaPagamento->descricao ?? 'Sem forma de pagamento' }}</p>
                                    </div>
                                </div>

                                @if ($pedido->descricao)
                                    <p class="mb-4 text-xs text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-left">
                                        Observação: {{ $pedido->descricao }}
                                    </p>
                                @endif

                                <div class="border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm whitespace-nowrap">
                                            <thead class="bg-gray-700 text-xs uppercase tracking-wide">
                                                <tr>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">#</th>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">Código</th>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">Cód. barras</th>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">Descrição</th>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">Und</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Quantidade</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Valor unit.</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                @forelse ($pedido->itens as $indice => $item)
                                                    <tr class="{{ $cancelado ? 'text-gray-400' : 'text-gray-700' }}">
                                                        <td class="px-3 py-2.5 text-gray-400">{{ $indice + 1 }}</td>
                                                        <td class="px-3 py-2.5 font-mono text-xs">{{ $item->produto->codigo_interno ?? '—' }}</td>
                                                        <td class="px-3 py-2.5 font-mono text-xs">{{ $item->codigo_barras ?: '—' }}</td>
                                                        <td class="px-3 py-2.5 font-medium">{{ $item->descricao }}</td>
                                                        <td class="px-3 py-2.5">{{ $item->unidade }}</td>
                                                        <td class="px-3 py-2.5 text-right">{{ $qtd($item->quantidade) }}</td>
                                                        <td class="px-3 py-2.5 text-right">{{ $dinheiro($item->valor_unitario) }}</td>
                                                        <td class="px-3 py-2.5 text-right font-medium">{{ $dinheiro($item->total()) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="8" class="px-3 py-6 text-center text-gray-400">Pedido sem itens.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="flex flex-wrap justify-end gap-x-6 gap-y-1 mt-4 text-sm text-gray-600">
                                    <span>Itens: <strong class="text-gray-800">{{ $pedido->itens->count() }}</strong></span>
                                    <span>Total: <strong class="{{ $cancelado ? 'text-red-600' : 'text-gray-800' }}">{{ $dinheiro($pedido->valor_total) }}</strong></span>
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