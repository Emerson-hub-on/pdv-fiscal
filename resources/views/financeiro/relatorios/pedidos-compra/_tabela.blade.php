@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $data = fn ($v, $formato = 'd/m/Y') => $v ? \Carbon\Carbon::parse($v)->format($formato) : '—';

    // Aberto ou enviado com a previsão de entrega vencida: dias de atraso
    $atraso = function ($pedido) {
        if (! in_array($pedido->status, ['aberto', 'enviado'], true) || ! $pedido->previsao_entrega) {
            return 0;
        }

        return max((int) $pedido->previsao_entrega->diffInDays(today(), false), 0);
    };

    $statusPedido = [
        'aberto'    => ['label' => 'Aberto', 'classe' => 'bg-amber-50 text-amber-700', 'ponto' => 'bg-amber-500'],
        'enviado'   => ['label' => 'Enviado', 'classe' => 'bg-blue-50 text-blue-700', 'ponto' => 'bg-blue-500'],
        'cancelado' => ['label' => 'Cancelado', 'classe' => 'bg-red-50 text-red-600', 'ponto' => 'bg-red-500'],
    ];

    $temRotaImprimir = \Illuminate\Support\Facades\Route::has('pedidos-compra.imprimir');
    $colunas = 8;
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Abertos</p>
        <p class="text-lg font-semibold text-amber-600">{{ number_format($totais->abertos, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Enviados</p>
        <p class="text-lg font-semibold text-blue-700">{{ number_format($totais->enviados, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Atrasados</p>
        <p class="text-lg font-semibold text-red-600">{{ number_format($totais->atrasados, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Cancelados</p>
        <p class="text-lg font-semibold text-gray-800">{{ number_format($totais->cancelados, 0, ',', '.') }}</p>
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
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Fornecedor</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">CPF/CNPJ</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Observação</th>
                    <th class="px-4 py-3 text-center font-medium text-amber-50">Itens</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($pedidos as $pedido)
                    @php
                        $situacao = $statusPedido[$pedido->status] ?? ['label' => ucfirst($pedido->status), 'classe' => 'bg-gray-100 text-gray-700', 'ponto' => 'bg-gray-400'];
                        $diasAtraso = $atraso($pedido);
                        $fornecedor = $pedido->fornecedor->nome_exibicao ?? null;
                    @endphp
                    <tr class="hover:bg-gray-200 transition">
                        <td class="px-4 py-3 font-medium text-gray-800">
                            @if ($temRotaImprimir)
                                <a href="{{ route('pedidos-compra.imprimir', $pedido) }}" target="_blank" class="hover:text-blue-600 hover:underline">{{ $pedido->numeroExibicao() }}</a>
                            @else
                                {{ $pedido->numeroExibicao() }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $data($pedido->data_pedido) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap {{ $diasAtraso > 0 ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                            {{ $data($pedido->previsao_entrega) }}
                            @if ($diasAtraso > 0)
                                <span class="text-xs font-normal">({{ $diasAtraso }} {{ $diasAtraso === 1 ? 'dia' : 'dias' }} de atraso)</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-800 max-w-xs truncate" title="{{ $fornecedor }}">{{ $fornecedor ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $pedido->fornecedor->documento_formatado ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap {{ $situacao['classe'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $situacao['ponto'] }}"></span>
                                {{ $situacao['label'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500 max-w-xs truncate" title="{{ $pedido->observacao }}">{{ $pedido->observacao ?: '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <button type="button" onclick="abrirItens(this)"
                                    data-nota="{{ $pedido->id }}"
                                    data-titulo="Pedido de compra {{ $pedido->numeroExibicao() }}"
                                    data-subtitulo="{{ $fornecedor ?? '' }}"
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
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Fornecedor</p>
                                        <p class="text-sm font-medium text-gray-800 truncate" title="{{ $fornecedor }}">{{ $fornecedor ?: '—' }}</p>
                                        <p class="text-xs text-gray-500 font-mono">{{ $pedido->fornecedor->documento_formatado ?? '' }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Pedido</p>
                                        <p class="text-sm font-medium text-gray-800">{{ $data($pedido->data_pedido) }}</p>
                                        <p class="text-xs text-gray-500">Nº {{ $pedido->numeroExibicao() }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Previsão de entrega</p>
                                        <p class="text-sm font-medium {{ $diasAtraso > 0 ? 'text-red-600' : 'text-gray-800' }}">{{ $data($pedido->previsao_entrega) }}</p>
                                        @if ($diasAtraso > 0)
                                            <p class="text-xs text-red-600">{{ $diasAtraso }} {{ $diasAtraso === 1 ? 'dia' : 'dias' }} de atraso</p>
                                        @endif
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Situação</p>
                                        <p class="text-sm font-medium text-gray-800">{{ $situacao['label'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $pedido->itens->count() }} {{ $pedido->itens->count() === 1 ? 'item' : 'itens' }}</p>
                                    </div>
                                </div>

                                @if ($pedido->observacao)
                                    <p class="mb-4 text-xs text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-left">
                                        Observação: {{ $pedido->observacao }}
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
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Estoque atual</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                @forelse ($pedido->itens as $indice => $item)
                                                    @php
                                                        // o estoque mora na variante quando o item é de uma variação
                                                        $estoqueAtual = $item->variante?->estoque ?? $item->produto?->estoque;
                                                    @endphp
                                                    <tr class="text-gray-700">
                                                        <td class="px-3 py-2.5 text-gray-400">{{ $indice + 1 }}</td>
                                                        <td class="px-3 py-2.5 font-mono text-xs">{{ $item->produto->codigo_interno ?? '—' }}</td>
                                                        <td class="px-3 py-2.5 font-mono text-xs">{{ $item->codigo_barras ?: '—' }}</td>
                                                        <td class="px-3 py-2.5 font-medium">{{ $item->descricao }}</td>
                                                        <td class="px-3 py-2.5">{{ $item->unidade }}</td>
                                                        <td class="px-3 py-2.5 text-right font-medium">{{ $qtd($item->quantidade) }}</td>
                                                        <td class="px-3 py-2.5 text-right {{ $estoqueAtual !== null && (float) $estoqueAtual <= 0 ? 'text-red-600' : 'text-gray-600' }}">{{ $estoqueAtual !== null ? $qtd($estoqueAtual) : '—' }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="7" class="px-3 py-6 text-center text-gray-400">Pedido sem itens.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <p class="mt-3 text-xs text-gray-400 text-right">Estoque atual é o saldo de hoje, não o da data do pedido.</p>
                            </template>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $colunas }}" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhum pedido encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    {{ $pedidos->links() }}
</div>
