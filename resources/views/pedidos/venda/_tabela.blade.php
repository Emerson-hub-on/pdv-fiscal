@php
    $badges = [
        'finalizado' => ['Finalizado', 'bg-green-100 text-green-700'],
        'faturado'   => ['Faturado',   'bg-blue-100 text-blue-700'],
        'cancelado'  => ['Cancelado',  'bg-gray-200 text-gray-600'],
    ];
@endphp

{{-- Tabela de largura fixa: texto longo é cortado com "…" (passe o mouse para ver tudo). Clique na linha para abrir. --}}
<table class="w-full text-sm table-fixed">
    <colgroup>
        <col class="w-[9%]">
        <col class="w-[10%]">
        <col class="w-[24%]">
        <col class="w-[15%]">
        <col class="w-[6%]">
        <col class="w-[12%]">
        <col class="w-[14%]">
        <col class="w-[10%]">
    </colgroup>
    <thead class="bg-gray-700 text-amber-50 text-xs uppercase">
        <tr>
            <th class="text-left px-2 py-2">Nº</th>
            <th class="text-left px-2 py-2">Data</th>
            <th class="text-left px-2 py-2">Cliente</th>
            <th class="text-left px-2 py-2">Operador</th>
            <th class="text-center px-2 py-2">Itens</th>
            <th class="text-right px-2 py-2">Total</th>
            <th class="text-left px-2 py-2">Status</th>
            <th class="text-right px-2 py-2">Ações</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
        @forelse ($pedidos as $pedido)
            @php
                $rotaLinha = $pedido->podeEditar()
                    ? route('pedidos-venda.edit', $pedido)
                    : route('pedidos-venda.imprimir', $pedido);
                $nomeCliente = $pedido->cliente?->nome_exibicao ?? $pedido->cliente?->nome;
            @endphp
            <tr class="h-14 hover:bg-gray-50">
                <td class="px-2 py-2 cursor-pointer font-medium" onclick="location.href='{{ $rotaLinha }}'">{{ $pedido->numeroExibicao() }}</td>
                <td class="px-2 py-2 cursor-pointer whitespace-nowrap" onclick="location.href='{{ $rotaLinha }}'">{{ $pedido->data_pedido->format('d/m/Y') }}</td>
                <td class="px-2 py-2 cursor-pointer truncate" title="{{ $nomeCliente }}" onclick="location.href='{{ $rotaLinha }}'">{{ $nomeCliente ?? '—' }}</td>
                <td class="px-2 py-2 cursor-pointer truncate" title="{{ $pedido->operador?->name }}" onclick="location.href='{{ $rotaLinha }}'">{{ $pedido->operador?->name ?? '—' }}</td>
                <td class="px-2 py-2 text-center cursor-pointer" onclick="location.href='{{ $rotaLinha }}'">{{ $pedido->itens_count }}</td>
                <td class="px-2 py-2 text-right cursor-pointer whitespace-nowrap" onclick="location.href='{{ $rotaLinha }}'">R$ {{ number_format($pedido->valor_total, 2, ',', '.') }}</td>
                <td class="px-2 py-2 cursor-pointer whitespace-nowrap" onclick="location.href='{{ $rotaLinha }}'">
                    <span class="inline-block whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badges[$pedido->status][1] ?? 'bg-gray-100 text-gray-600' }}">{{ $badges[$pedido->status][0] ?? $pedido->status }}</span>
                </td>

                {{-- sem truncate aqui: o menu de ações é absoluto e seria cortado --}}
                <td class="px-2 py-2 text-right relative">
                    <button type="button" onclick="toggleAcoesLinha({{ $pedido->id }})"
                            class="text-gray-500 hover:text-gray-800 px-2 py-1 rounded hover:bg-gray-100">
                        ⋮
                    </button>

                    <div id="dropdown-acoes-{{ $pedido->id }}"
                         class="hidden absolute right-4 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg py-1 min-w-48 z-50 text-left">
                        @if ($pedido->podeEditar())
                            <a href="{{ route('pedidos-venda.edit', $pedido) }}"
                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Editar</a>
                        @endif

                        <a href="{{ route('pedidos-venda.imprimir', $pedido) }}" target="_blank"
                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Imprimir / PDF</a>

                        @if ($pedido->podeCancelar())
                            <div class="border-t border-gray-100 my-1"></div>
                            <form method="POST" action="{{ route('pedidos-venda.cancelar', $pedido) }}"
                                  onsubmit="return confirm('Cancelar o pedido {{ $pedido->numeroExibicao() }}?')">
                                @csrf @method('PATCH')
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">Cancelar pedido</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="px-4 py-8 text-center text-gray-400">Nenhum pedido de venda encontrado.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="p-4">{{ $pedidos->links() }}</div>
