@php
    $badges = [
        'aberta'               => 'bg-amber-100 text-amber-700',
        'em_analise'           => 'bg-purple-100 text-purple-700',
        'aguardando_aprovacao' => 'bg-orange-100 text-orange-700',
        'aguardando_peca'      => 'bg-yellow-100 text-yellow-700',
        'em_andamento'         => 'bg-blue-100 text-blue-700',
        'concluida'            => 'bg-green-100 text-green-700',
        'entregue'             => 'bg-teal-100 text-teal-700',
        'cancelada'            => 'bg-gray-200 text-gray-600',
    ];
@endphp

{{-- Tabela de largura fixa: texto longo é cortado com "…" (passe o mouse para ver tudo). Clique na linha para abrir. --}}
<table class="w-full text-sm table-fixed">
    <colgroup>
        <col class="w-[9%]">
        <col class="w-[9%]">
        <col class="w-[20%]">
        <col class="w-[19%]">
        <col class="w-[9%]">
        <col class="w-[11%]">
        <col class="w-[13%]">
        <col class="w-[10%]">
    </colgroup>
    <thead class="bg-gray-700 text-amber-50 text-xs uppercase">
        <tr>
            <th class="text-left px-2 py-2">Nº</th>
            <th class="text-left px-2 py-2">Abertura</th>
            <th class="text-left px-2 py-2">Cliente</th>
            <th class="text-left px-2 py-2">Equipamento</th>
            <th class="text-left px-2 py-2">Previsão</th>
            <th class="text-right px-2 py-2">Total</th>
            <th class="text-left px-2 py-2">Status</th>
            <th class="text-right px-2 py-2">Ações</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
        @forelse ($ordens as $ordem)
            @php
                $rotaLinha = $ordem->podeEditar()
                    ? route('ordens-servico.edit', $ordem)
                    : route('ordens-servico.imprimir', $ordem);
                $nomeCliente = $ordem->nomeCliente();
                $textoEquip = trim($ordem->equipamento . ' ' . ($ordem->marca ?? '') . ' ' . ($ordem->modelo ?? ''));
            @endphp
            <tr class="h-14 hover:bg-gray-50">
                <td class="px-2 py-2 cursor-pointer font-medium" onclick="location.href='{{ $rotaLinha }}'">
                    {{ $ordem->numeroExibicao() }}
                    @if ($ordem->prioridade === 'urgente' && $ordem->podeEditar())
                        <span class="block text-[10px] font-semibold uppercase text-red-600">Urgente</span>
                    @endif
                </td>
                <td class="px-2 py-2 cursor-pointer whitespace-nowrap" onclick="location.href='{{ $rotaLinha }}'">{{ $ordem->data_abertura->format('d/m/Y') }}</td>
                <td class="px-2 py-2 cursor-pointer truncate" title="{{ $nomeCliente }}" onclick="location.href='{{ $rotaLinha }}'">{{ $nomeCliente ?? '—' }}</td>
                <td class="px-2 py-2 cursor-pointer truncate" title="{{ $textoEquip }}" onclick="location.href='{{ $rotaLinha }}'">{{ $textoEquip }}</td>
                <td class="px-2 py-2 cursor-pointer whitespace-nowrap {{ $ordem->atrasada() ? 'text-red-600 font-medium' : '' }}"
                    title="{{ $ordem->atrasada() ? 'Previsão vencida' : '' }}" onclick="location.href='{{ $rotaLinha }}'">
                    {{ $ordem->previsao_entrega?->format('d/m/Y') ?? '—' }}
                </td>
                <td class="px-2 py-2 text-right cursor-pointer whitespace-nowrap" onclick="location.href='{{ $rotaLinha }}'">R$ {{ number_format($ordem->valor_total, 2, ',', '.') }}</td>
                <td class="px-2 py-2 cursor-pointer whitespace-nowrap" onclick="location.href='{{ $rotaLinha }}'">
                    <span class="inline-block whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badges[$ordem->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $ordem->statusTexto() }}</span>
                </td>

                {{-- sem truncate aqui: o menu de ações é absoluto e seria cortado --}}
                <td class="px-2 py-2 text-right relative">
                    <button type="button" onclick="toggleAcoesLinha({{ $ordem->id }})"
                            class="text-gray-500 hover:text-gray-800 px-2 py-1 rounded hover:bg-gray-100">
                        ⋮
                    </button>

                    <div id="dropdown-acoes-{{ $ordem->id }}"
                         class="hidden absolute right-4 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg py-1 min-w-56 z-50 text-left">
                        @if ($ordem->podeEditar())
                            <a href="{{ route('ordens-servico.edit', $ordem) }}"
                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Editar</a>
                        @endif

                        <a href="{{ route('ordens-servico.imprimir', $ordem) }}" target="_blank"
                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Imprimir / PDF</a>

                        <a href="{{ route('ordens-servico.cupom', $ordem) }}" target="_blank"
                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Imprimir cupom (80 mm)</a>

                        @foreach ($ordem->proximosStatus() as $novoStatus => $rotuloAcao)
                            @if ($loop->first)
                                <div class="border-t border-gray-100 my-1"></div>
                            @endif
                            <form method="POST" action="{{ route('ordens-servico.status', $ordem) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $novoStatus }}">
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ $rotuloAcao }}</button>
                            </form>
                        @endforeach

                        @if ($ordem->podeCancelar())
                            <div class="border-t border-gray-100 my-1"></div>
                            <form method="POST" action="{{ route('ordens-servico.cancelar', $ordem) }}"
                                  onsubmit="return confirm('Cancelar a ordem de serviço {{ $ordem->numeroExibicao() }}?')">
                                @csrf @method('PATCH')
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">Cancelar ordem de serviço</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="px-4 py-8 text-center text-gray-400">Nenhuma ordem de serviço encontrada.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="p-4">{{ $ordens->links() }}</div>