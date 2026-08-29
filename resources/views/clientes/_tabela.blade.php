<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-blue-700 rounded-lg font-medium text-xs uppercase tracking-wide">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Nome</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">CPF/CNPJ</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">IE</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">UF</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Status</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($clientes as $cliente)
                <tr class="hover:bg-gray-200 transition">
                    <td class="px-4 py-3 font-medium text-gray-800">
                        {{ $cliente->nome }}
                        @if ($cliente->tipo_pessoa === 'juridica' && $cliente->nome_fantasia)
                            <span class="text-xs text-gray-400 block font-normal">{{ $cliente->nome_fantasia }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $cliente->cpf_cnpj_formatado }}</td>
                    <td class="px-4 py-3 text-gray-600">
                        {{ match($cliente->indicador_ie) {
                            'contribuinte' => 'Contribuinte',
                            'isento' => 'Isento',
                            default => 'Não Contribuinte',
                        } }}
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $cliente->uf ?? '—' }}</td>
                    <td class="px-4 py-3">
                        @if ($cliente->ativo)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                Ativo
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-600">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                Inativo
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <a href="{{ route('clientes.edit', $cliente) }}" class="text-blue-600 hover:text-blue-700 font-medium">Editar</a>
                            <form action="{{ route('clientes.toggleAtivo', $cliente) }}" method="POST">
                                @csrf
                                <button type="submit" class="text-orange-600 hover:text-orange-700 font-medium">
                                    {{ $cliente->ativo ? 'Inativar' : 'Reativar' }}
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhum cliente encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $clientes->links() }}
</div>