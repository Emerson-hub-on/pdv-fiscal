<div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 text-left">
            <tr>
                <th class="px-4 py-3 font-medium">Entrada</th>
                <th class="px-4 py-3 font-medium">Nota</th>
                <th class="px-4 py-3 font-medium">Fornecedor</th>
                <th class="px-4 py-3 font-medium text-right">Total</th>
                <th class="px-4 py-3 font-medium">Status</th>
                <th class="px-4 py-3 font-medium text-right">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entradas as $entrada)
                <tr class="border-t border-gray-100 hover:bg-gray-50">
                    <td class="px-4 py-3 whitespace-nowrap">{{ $entrada->data_entrada->format('d/m/Y') }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="font-medium">{{ $entrada->numero }}</span>
                        @if ($entrada->serie)
                            <span class="text-gray-400">/ {{ $entrada->serie }}</span>
                        @endif
                        <span class="ml-1 text-xs text-gray-400">mod. {{ $entrada->modelo }}</span>
                    </td>
                    <td class="px-4 py-3">{{ $entrada->fornecedor->nome_exibicao }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        R$ {{ number_format($entrada->valor_total, 2, ',', '.') }}
                    </td>
                    <td class="px-4 py-3">
                        @if ($entrada->isFinalizada())
                            <span class="inline-block rounded-full bg-green-100 text-green-800 text-xs font-medium px-2 py-0.5">Finalizada</span>
                        @elseif ($entrada->isRascunho())
                            <span class="inline-block rounded-full bg-amber-100 text-amber-800 text-xs font-medium px-2 py-0.5">Rascunho</span>
                        @else
                            <span class="inline-block rounded-full bg-gray-200 text-gray-700 text-xs font-medium px-2 py-0.5">Cancelada</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('entradas-nota.edit', $entrada) }}"
                           class="text-blue-600 hover:underline">
                            {{ $entrada->isRascunho() ? 'Editar' : 'Ver' }}
                        </a>

                        @if ($entrada->isRascunho())
                            <form method="POST" action="{{ route('entradas-nota.destroy', $entrada) }}"
                                  class="inline"
                                  onsubmit="return confirm('Excluir este rascunho?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ml-3 text-red-600 hover:underline cursor-pointer">Excluir</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-gray-400">Nenhuma entrada encontrada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $entradas->links() }}
</div>
