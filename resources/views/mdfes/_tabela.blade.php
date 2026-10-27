<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Nº / Série</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Percurso</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">NF-e</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Valor da carga</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Peso (kg)</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Status</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($mdfes as $mdfe)
                @php
                    $badge = match ($mdfe->status) {
                        'emitido'   => ['bg-green-50 text-green-700', 'bg-green-500', 'Emitido'],
                        'encerrado' => ['bg-slate-100 text-slate-700', 'bg-slate-500', 'Encerrado'],
                        'cancelado' => ['bg-red-50 text-red-600', 'bg-red-500', 'Cancelado'],
                        default     => ['bg-amber-50 text-amber-700', 'bg-amber-500', 'Rascunho'],
                    };
                @endphp
                <tr class="hover:bg-gray-200 transition">
                    <td class="px-4 py-3 font-medium text-gray-800 font-mono">
                        {{ $mdfe->numero ? $mdfe->numero . ' / ' . $mdfe->serie : '—' }}
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $mdfe->uf_inicio }} → {{ $mdfe->uf_fim }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $mdfe->quantidade_nfe }}</td>
                    <td class="px-4 py-3 text-gray-600">R$ {{ number_format($mdfe->valor_carga, 2, ',', '.') }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ number_format($mdfe->peso_carga_kg, 3, ',', '.') }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $badge[0] }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $badge[1] }}"></span>
                            {{ $badge[2] }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <a href="{{ route('mdfes.show', $mdfe) }}" class="text-gray-600 hover:text-gray-800 font-medium">Ver</a>
                            @if ($mdfe->status === 'rascunho')
                                <a href="{{ route('mdfes.edit', $mdfe) }}" class="text-blue-600 hover:text-blue-700 font-medium">Editar</a>
                                <form action="{{ route('mdfes.destroy', $mdfe) }}" method="POST"
                                      onsubmit="return confirm('Excluir este rascunho de MDF-e?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-700 font-medium">Excluir</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhum MDF-e encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $mdfes->links() }}
</div>