<table class="w-full text-sm">
    <thead class="bg-gray-700 text-amber-50 text-xs uppercase">
        <tr>
            <th class="text-left px-4 py-2">Número</th>
            <th class="text-left px-4 py-2">Série</th>
            <th class="text-left px-4 py-2">Fornecedor</th>
            <th class="text-left px-4 py-2">CPF/CNPJ</th>
            <th class="text-right px-4 py-2">Total</th>
            <th class="text-left px-4 py-2">Status</th>
            <th class="text-left px-4 py-2">Data de entrada</th>
            <th class="text-right px-4 py-2">Ações</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
        @forelse ($entradas as $entrada)
            @php $rotaLinha = route('entradas-nota.edit', $entrada); @endphp
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 cursor-pointer" onclick="location.href='{{ $rotaLinha }}'">{{ $entrada->numero }}</td>
                <td class="px-4 py-2 cursor-pointer" onclick="location.href='{{ $rotaLinha }}'">{{ $entrada->serie ?? '—' }}</td>
                <td class="px-4 py-2 cursor-pointer" onclick="location.href='{{ $rotaLinha }}'">{{ $entrada->fornecedor->nome_exibicao }}</td>
                <td class="px-4 py-2 cursor-pointer" onclick="location.href='{{ $rotaLinha }}'">{{ $entrada->fornecedor->documento_formatado }}</td>
                <td class="px-4 py-2 text-right cursor-pointer" onclick="location.href='{{ $rotaLinha }}'">R$ {{ number_format($entrada->valor_total, 2, ',', '.') }}</td>
                <td class="px-4 py-2 cursor-pointer" onclick="location.href='{{ $rotaLinha }}'">{{ ucfirst($entrada->status) }}</td>
                <td class="px-4 py-2 cursor-pointer" onclick="location.href='{{ $rotaLinha }}'">{{ $entrada->data_entrada->format('d/m/Y') }}</td>

                <td class="px-4 py-2 text-right relative">
                    <button type="button" onclick="toggleAcoesLinha({{ $entrada->id }})"
                            class="text-gray-500 hover:text-gray-800 px-2 py-1 rounded hover:bg-gray-100">
                        ⋮
                    </button>

                    <div id="dropdown-acoes-{{ $entrada->id }}"
                         class="hidden absolute right-4 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg py-1 min-w-44 z-50 text-left">
                        @if ($entrada->isRascunho())
                            <a href="{{ $rotaLinha }}"
                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Editar</a>

                            <div class="border-t border-gray-100 my-1"></div>

                            <form method="POST" action="{{ route('entradas-nota.destroy', $entrada) }}"
                                  onsubmit="return confirm('Excluir esta entrada em rascunho? Esta ação não pode ser desfeita.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">Excluir rascunho</button>
                            </form>
                        @else
                            <a href="{{ $rotaLinha }}"
                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Visualizar</a>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="px-4 py-8 text-center text-gray-400">Nenhuma entrada encontrada.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="p-4">{{ $entradas->links() }}</div>
