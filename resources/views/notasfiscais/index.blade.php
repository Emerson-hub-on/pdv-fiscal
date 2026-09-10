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
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach ($notas as $nota)
                <tr class="hover:bg-gray-50 cursor-pointer" onclick="location.href='{{ route('notasfiscais.show', $nota) }}'">
                    <td class="px-4 py-2">{{ $nota->numero ?? '—' }}</td>
                    <td class="px-4 py-2">{{ $nota->cliente->nome }}</td>
                    <td class="px-4 py-2">{{ ucfirst($nota->status) }}</td>
                    <td class="px-4 py-2 text-right">R$ {{ number_format($nota->valor_total, 2, ',', '.') }}</td>
                    <td class="px-4 py-2">{{ $nota->created_at->format('d/m/Y H:i') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="p-4">{{ $notas->links() }}</div>
</div>
@endsection