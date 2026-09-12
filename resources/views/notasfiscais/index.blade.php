@extends('layouts.app')

@section('titulo', 'Notas Fiscais')

@section('conteudo')
<div class="bg-white rounded-lg shadow overflow-hidden">
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
                    <td class="px-4 py-2">
                        <div class="flex justify-end gap-3 text-xs">
                            <a href="{{ route('notasfiscais.previsualizar', $nota) }}" target="_blank"
                               class="text-gray-600 hover:underline">Pré-visualizar</a>

                            @if ($nota->status === 'rascunho')
                                <a href="{{ route('notasfiscais.edit', $nota) }}" class="text-blue-600 hover:underline">Editar</a>

                                <form method="POST" action="{{ route('notasfiscais.emitir', $nota) }}" onsubmit="return confirm('Confirma a emissão desta NF-e?')">
                                    @csrf
                                    <button type="submit" class="text-green-700 hover:underline">Emitir</button>
                                </form>
                            @elseif ($nota->status === 'emitida')
                                <a href="{{ route('notasfiscais.xml', $nota) }}" class="text-gray-600 hover:underline">XML</a>
                                <a href="{{ route('notasfiscais.cancelar-form', $nota) }}" class="text-red-600 hover:underline">Cancelar</a>
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