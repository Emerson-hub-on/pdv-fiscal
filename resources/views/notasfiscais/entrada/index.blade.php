@extends('layouts.app')

@section('titulo', 'Nota Fiscal - Entrada')

@section('conteudo')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Nota Fiscal - Entrada</h1>
        <a href="{{ route('entradas-nota.create') }}"
           class="bg-gray-900 hover:bg-gray-700 text-white text-sm font-medium px-4 py-2 rounded transition">
            + Nova entrada
        </a>
    </div>

    <form method="GET" action="{{ route('entradas-nota.index') }}" class="flex flex-wrap items-end gap-3 mb-5">
        <div class="flex-1 min-w-64">
            <label class="block text-xs text-gray-500 mb-1">Buscar</label>
            <input type="text" name="busca" value="{{ request('busca') }}"
                   placeholder="Fornecedor, número ou chave de acesso"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Status</label>
            <select name="status"
                    class="rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                <option value="">Todos</option>
                <option value="rascunho" @selected(request('status') === 'rascunho')>Rascunho</option>
                <option value="finalizada" @selected(request('status') === 'finalizada')>Finalizada</option>
            </select>
        </div>
        <button type="submit"
                class="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-medium px-4 py-2 rounded transition">
            Filtrar
        </button>
    </form>

    @include('notasfiscais.entrada._tabela', ['entradas' => $entradas])
@endsection