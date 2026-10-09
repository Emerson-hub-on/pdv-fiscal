@extends('layouts.app')

@section('titulo', 'Nova entrada de nota')
@section('body-class', 'sidebar-oculta conteudo-largo')

@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Nova entrada de nota</h1>

    <form method="POST" action="{{ route('entradas-nota.store') }}">
        @csrf

        @include('notasfiscais.entrada._form')

        <div class="flex items-center gap-3 mt-6">
            <button type="submit" name="acao" value="salvar"
                    class="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-medium px-5 py-2 rounded transition cursor-pointer">
                Salvar rascunho
            </button>
            <button type="submit" name="acao" value="finalizar"
                    onclick="return confirm('Finalizar a entrada? O estoque será atualizado e a nota não poderá mais ser alterada.')"
                    class="bg-gray-900 hover:bg-gray-700 text-white text-sm font-medium px-5 py-2 rounded transition cursor-pointer">
                Finalizar entrada
            </button>
            <a href="{{ route('entradas-nota.index') }}" class="text-sm text-gray-500 hover:underline ml-2">Cancelar</a>
        </div>
    </form>
@endsection