@extends('layouts.app')

@section('titulo', 'Entrada de nota')
@section('body-class', 'sidebar-oculta conteudo-largo')

@section('conteudo')
    <div class="flex items-center gap-3 mb-6">
        <h1 class="text-2xl font-bold">
            Entrada {{ $entrada->numero }}{{ $entrada->serie ? ' / ' . $entrada->serie : '' }}
        </h1>
        @if ($somenteLeitura)
            <span class="inline-block rounded-full bg-green-100 text-green-800 text-xs font-medium px-2 py-0.5">
                Finalizada em {{ $entrada->finalizada_em?->format('d/m/Y H:i') }}
            </span>
        @else
            <span class="inline-block rounded-full bg-amber-100 text-amber-800 text-xs font-medium px-2 py-0.5">Rascunho</span>
        @endif
    </div>

    <form method="POST" action="{{ route('entradas-nota.update', $entrada) }}">
        @csrf
        @method('PUT')

        @include('notasfiscais.entrada._form')

        <div class="flex items-center gap-3 mt-6">
            @unless ($somenteLeitura)
                <button type="submit" name="acao" value="salvar"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-medium px-5 py-2 rounded transition cursor-pointer">
                    Salvar rascunho
                </button>
                <button type="submit" name="acao" value="finalizar"
                        onclick="return confirm('Finalizar a entrada? O estoque será atualizado e a nota não poderá mais ser alterada.')"
                        class="bg-gray-900 hover:bg-gray-700 text-white text-sm font-medium px-5 py-2 rounded transition cursor-pointer">
                    Finalizar entrada
                </button>
            @endunless
            <a href="{{ route('entradas-nota.index') }}" class="text-sm text-gray-500 hover:underline ml-2">Voltar</a>
        </div>
    </form>
@endsection