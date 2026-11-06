@extends('layouts.app')

@section('titulo', 'Editar Série de NF-e')

@section('conteudo')
@php extract(config('estilo.formulario')); @endphp
<form method="POST" action="{{ route('series-nfe.update', $serieNfe) }}"
      class="{{ $cartaoCls }} max-w-lg">
    @csrf @method('PUT')

    <x-form.cabecalho rotulo="Faturamento · Numeração de NF-e" titulo="Editar Série de NF-e"
                      :voltar="route('series-nfe.index')" voltar-titulo="Voltar para a lista" />

    <div class="p-6 flex flex-col gap-4">
    <div class="bg-amber-50 text-amber-800 ring-1 ring-amber-200 rounded-xl px-3 py-2 text-xs">
        Use este campo apenas para corrigir numeração após um número "queimado" (rejeitado ou já registrado
        na SEFAZ, mas sem confirmação local) — evite editar sem necessidade real.
    </div>

    <div>
        <label class="{{ $labelCls }}">Série</label>
        <input type="number" name="serie" value="{{ old('serie', $serieNfe->serie) }}" required
               class="{{ $inputCls }}">
    </div>

    <div>
        <label class="{{ $labelCls }}">Último número emitido</label>
        <input type="number" name="numero_atual" value="{{ old('numero_atual', $serieNfe->numero_atual) }}" required
               class="{{ $inputCls }}">
        <p class="text-xs text-gray-500 mt-1">A próxima nota emitida usará este número + 1.</p>
    </div>

    <div>
        <label class="{{ $labelCls }}">Descrição</label>
        <input type="text" name="descricao" value="{{ old('descricao', $serieNfe->descricao) }}"
               class="{{ $inputCls }}">
    </div>

    <label class="flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="ativa" value="1" class="accent-[#10284A]" @checked(old('ativa', $serieNfe->ativa))>
        Ativa
    </label>

    <button type="submit" class="{{ $botaoPrimarioCls }} self-end">
        <x-form.icone nome="check" />
        Salvar
    </button>
    </div>
</form>
@endsection