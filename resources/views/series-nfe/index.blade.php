@extends('layouts.app')

@section('titulo', 'Séries de NF-e')

@section('conteudo')
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="flex justify-between items-center p-4 border-b border-gray-100">
        <h1 class="text-lg font-semibold">Séries de NF-e</h1>
    </div>

    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
                <th class="text-left px-4 py-2">Série</th>
                <th class="text-left px-4 py-2">Último número emitido</th>
                <th class="text-left px-4 py-2">Descrição</th>
                <th class="text-center px-4 py-2">Ativa</th>
                <th class="text-right px-4 py-2">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach ($series as $serie)
                <tr>
                    <td class="px-4 py-2 font-mono">{{ $serie->serie }}</td>
                    <td class="px-4 py-2">{{ $serie->numero_atual }} <span class="text-gray-400">(próxima: {{ $serie->numero_atual + 1 }})</span></td>
                    <td class="px-4 py-2">{{ $serie->descricao ?? '—' }}</td>
                    <td class="px-4 py-2 text-center">{{ $serie->ativa ? 'Sim' : 'Não' }}</td>
                    <td class="px-4 py-2 text-right">
                        <a href="{{ route('series-nfe.edit', $serie) }}" class="text-blue-600 hover:underline text-xs">Editar</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection