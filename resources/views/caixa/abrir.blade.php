@extends('layouts.app')

@section('titulo', 'Abrir Caixa')

@section('conteudo')
    <div class="max-w-md mx-auto bg-white p-6 rounded shadow mt-10">
        <h1 class="text-xl font-bold mb-6">Abertura de Caixa</h1>

        <form action="{{ route('caixa.abrir') }}" method="POST">
            @csrf
            <label class="block text-sm font-medium mb-1">Valor inicial em caixa (troco)</label>
            <input type="number" step="0.01" name="valor_abertura" required
                   class="w-full border rounded px-3 py-2 mb-4">

            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded font-semibold">
                Abrir Caixa
            </button>
        </form>
    </div>
@endsection