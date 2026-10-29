<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Nome</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">CNPJ</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Recebe</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Status</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($credenciadoras as $credenciadora)
                <tr class="hover:bg-gray-200 transition">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $credenciadora->nome }}</td>
                    <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $credenciadora->cnpj_formatado }}</td>
                    <td class="px-4 py-3 text-gray-600">
                        @forelse ($credenciadora->meios as $meio)
                            <span class="inline-block px-2 py-0.5 mr-1 mb-1 rounded-full text-xs bg-gray-100 text-gray-700">
                                {{ \App\Models\FormaPagamento::MEIOS[$meio->meio_pagamento] ?? $meio->meio_pagamento }}
                            </span>
                        @empty
                            <span class="text-xs text-amber-600">Nenhum meio vinculado</span>
                        @endforelse
                    </td>
                    <td class="px-4 py-3">
                        @if ($credenciadora->ativo)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Ativa
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-600">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Inativa
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <a href="{{ route('credenciadoras.edit', $credenciadora) }}" class="text-blue-600 hover:text-blue-700 font-medium">Editar</a>
                            <form action="{{ route('credenciadoras.toggleAtivo', $credenciadora) }}" method="POST">
                                @csrf
                                <button type="submit" class="text-orange-600 hover:text-orange-700 font-medium">
                                    {{ $credenciadora->ativo ? 'Inativar' : 'Reativar' }}
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhuma credenciadora encontrada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $credenciadoras->links() }}
</div>
