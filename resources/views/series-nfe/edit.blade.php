@extends('layouts.app')

@section('titulo', 'Editar Série de NF-e')

@section('conteudo')
<form method="POST" action="{{ route('series-nfe.update', $serieNfe) }}"
      class="bg-white rounded-lg shadow p-6 max-w-lg flex flex-col gap-4">
    @csrf @method('PUT')

    <h1 class="text-lg font-semibold">Editar Série de NF-e</h1>

    <div class="bg-amber-50 text-amber-800 border border-amber-200 rounded-lg px-3 py-2 text-xs">
        Use este campo apenas para corrigir numeração após um número "queimado" (rejeitado ou já registrado
        na SEFAZ, mas sem confirmação local) — evite editar sem necessidade real.
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Série</label>
        <input type="number" name="serie" value="{{ old('serie', $serieNfe->serie) }}" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Último número emitido</label>
        <input type="number" name="numero_atual" value="{{ old('numero_atual', $serieNfe->numero_atual) }}" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <p class="text-xs text-gray-500 mt-1">A próxima nota emitida usará este número + 1.</p>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
        <input type="text" name="descricao" value="{{ old('descricao', $serieNfe->descricao) }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </div>

    <label class="flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="ativa" value="1" @checked(old('ativa', $serieNfe->ativa))>
        Ativa
    </label>

    <button type="submit" class="bg-gray-800 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-gray-700 w-fit">
        Salvar
    </button>
</form>
@endsection