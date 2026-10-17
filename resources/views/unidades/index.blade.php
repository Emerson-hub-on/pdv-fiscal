@extends('layouts.app')

@section('titulo', 'Unidades')

@section('conteudo')
<div class="bg-white rounded-lg shadow overflow-hidden">
    <form method="GET" action="{{ route('unidades.index') }}" class="flex flex-wrap items-center gap-2 p-4 border-b border-gray-100">
        <h1 class="text-lg font-semibold mr-1">Unidades</h1>
        <input type="text" name="busca" placeholder="Sigla ou descrição" value="{{ request('busca') }}"
               class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-48">
        <button type="submit" class="bg-gray-800 text-white rounded-lg px-3 py-1.5 text-sm hover:bg-gray-700">Buscar</button>
        @if (request()->filled('busca'))
            <a href="{{ route('unidades.index') }}" class="text-sm text-gray-500 hover:underline">Limpar</a>
        @endif
        <a href="{{ route('unidades.create') }}"
           class="ml-auto bg-gray-700 text-amber-50 rounded-lg px-4 py-2 text-sm hover:bg-gray-500">+ Nova unidade</a>
    </form>

    <table class="w-full text-sm table-fixed">
        <colgroup>
            <col class="w-[14%]"><col class="w-[36%]"><col class="w-[20%]"><col class="w-[14%]"><col class="w-[16%]">
        </colgroup>
        <thead class="bg-gray-700 text-amber-50 text-xs uppercase">
            <tr>
                <th class="text-left px-3 py-2">Sigla</th>
                <th class="text-left px-3 py-2">Descrição</th>
                <th class="text-left px-3 py-2">Fracionada</th>
                <th class="text-left px-3 py-2">Situação</th>
                <th class="text-right px-3 py-2">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($unidades as $u)
                <tr class="h-12 hover:bg-gray-50">
                    <td class="px-3 py-2 font-mono font-medium">{{ $u->sigla }}</td>
                    <td class="px-3 py-2 truncate" title="{{ $u->descricao }}">{{ $u->descricao }}</td>
                    <td class="px-3 py-2">{{ $u->permite_fracionado ? 'Sim' : 'Não' }}</td>
                    <td class="px-3 py-2">
                        <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $u->ativo ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' }}">
                            {{ $u->ativo ? 'Ativa' : 'Inativa' }}
                        </span>
                    </td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">
                        <a href="{{ route('unidades.edit', $u) }}" class="text-gray-700 hover:underline mr-3">Editar</a>
                        <form method="POST" action="{{ route('unidades.toggle-ativo', $u) }}" class="inline">
                            @csrf @method('PATCH')
                            <button type="submit" class="{{ $u->ativo ? 'text-red-600' : 'text-green-700' }} hover:underline">
                                {{ $u->ativo ? 'Inativar' : 'Reativar' }}
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Nenhuma unidade encontrada.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="p-4">{{ $unidades->links() }}</div>
</div>
@endsection
