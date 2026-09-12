@extends('layouts.app')

@section('titulo', 'Notas Fiscais')

@section('conteudo')
@include('notasfiscais._recalculo_flash')

<div class="bg-white rounded-lg shadow overflow-visible">
    <div class="flex justify-between items-center p-4 border-b border-gray-100">
        <h1 class="text-lg font-semibold">Notas Fiscais</h1>
        <a href="{{ route('notasfiscais.create') }}"
           class="bg-gray-800 text-white rounded-lg px-4 py-2 text-sm hover:bg-gray-700">Nova nota</a>
    </div>

    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
                <th class="text-left px-4 py-2">Número</th>
                <th class="text-left px-4 py-2">Cliente</th>
                <th class="text-left px-4 py-2">Status</th>
                <th class="text-right px-4 py-2">Total</th>
                <th class="text-left px-4 py-2">Data</th>
                <th class="text-right px-4 py-2">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach ($notas as $nota)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 cursor-pointer" onclick="location.href='{{ route('notasfiscais.show', $nota) }}'">{{ $nota->numero ?? '—' }}</td>
                    <td class="px-4 py-2 cursor-pointer" onclick="location.href='{{ route('notasfiscais.show', $nota) }}'">{{ $nota->cliente->nome }}</td>
                    <td class="px-4 py-2 cursor-pointer" onclick="location.href='{{ route('notasfiscais.show', $nota) }}'">{{ ucfirst($nota->status) }}</td>
                    <td class="px-4 py-2 text-right cursor-pointer" onclick="location.href='{{ route('notasfiscais.show', $nota) }}'">R$ {{ number_format($nota->valor_total, 2, ',', '.') }}</td>
                    <td class="px-4 py-2 cursor-pointer" onclick="location.href='{{ route('notasfiscais.show', $nota) }}'">{{ $nota->created_at->format('d/m/Y H:i') }}</td>

                    <td class="px-4 py-2 text-right relative">
                        <button type="button" onclick="toggleAcoesLinha({{ $nota->id }})"
                                class="text-gray-500 hover:text-gray-800 px-2 py-1 rounded hover:bg-gray-100">
                            ⋮
                        </button>

                        <div id="dropdown-acoes-{{ $nota->id }}"
                             class="hidden absolute right-4 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg py-1 min-w-44 z-50 text-left">
                            <a href="{{ route('notasfiscais.previsualizar', $nota) }}" target="_blank"
                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Pré-visualizar</a>

                            @if ($nota->status === 'rascunho')
                                <a href="{{ route('notasfiscais.edit', $nota) }}"
                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Editar</a>

                                <form method="POST" action="{{ route('notasfiscais.recalcular', $nota) }}"
                                      onsubmit="return confirm('Recalcular dados fiscais dos itens a partir do cadastro atual dos produtos?')">
                                    @csrf
                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Recalcular</button>
                                </form>

                                <div class="border-t border-gray-100 my-1"></div>

                                <form method="POST" action="{{ route('notasfiscais.emitir', $nota) }}" onsubmit="return confirm('Confirma a emissão desta NF-e?')">
                                    @csrf
                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-green-700 font-medium hover:bg-green-50">Emitir</button>
                                </form>
                            @elseif ($nota->status === 'emitida')
                                <a href="{{ route('notasfiscais.xml', $nota) }}"
                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Baixar XML</a>

                                <div class="border-t border-gray-100 my-1"></div>

                                <a href="{{ route('notasfiscais.cancelar-form', $nota) }}"
                                   class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">Cancelar</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="p-4">{{ $notas->links() }}</div>
</div>
@endsection

@section('scripts')
<script>
function toggleAcoesLinha(id) {
    document.querySelectorAll('[id^="dropdown-acoes-"]').forEach(el => {
        if (el.id !== `dropdown-acoes-${id}`) el.classList.add('hidden');
    });
    document.getElementById(`dropdown-acoes-${id}`).classList.toggle('hidden');
}

document.addEventListener('click', (e) => {
    document.querySelectorAll('[id^="dropdown-acoes-"]').forEach(dropdown => {
        const container = dropdown.closest('td');
        if (!dropdown.classList.contains('hidden') && container && !container.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
});
</script>
@endsection