@extends('layouts.app')

@section('titulo', 'Fechar Caixa')

@section('conteudo')
    <div class="max-w-md mx-auto bg-white p-6 rounded shadow mt-10">
        <h1 class="text-xl font-bold mb-6">Fechamento de Caixa</h1>

        <p class="mb-2 text-sm">Valor de abertura: <strong>R$ {{ number_format($caixa->valor_abertura, 2, ',', '.') }}</strong></p>
        <p class="mb-4 text-sm">Total vendido: <strong>R$ {{ number_format($caixa->totalVendido(), 2, ',', '.') }}</strong></p>
        <p class="mb-4 text-sm text-blue-700">Valor esperado em caixa: <strong>R$ {{ number_format($valorEsperado, 2, ',', '.') }}</strong></p>

        <form action="{{ route('caixa.fechar') }}" method="POST">
            @csrf
            <label class="block text-sm font-medium mb-1">Valor contado no caixa</label>
            <input type="number" step="0.01" name="valor_fechamento_informado" required
                   class="w-full border rounded px-3 py-2 mb-4">

            <label class="block text-sm font-medium mb-1">Observação (opcional)</label>
            <textarea name="observacao" rows="2" class="w-full border rounded px-3 py-2 mb-4"></textarea>

            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 rounded font-semibold">
                Fechar Caixa
            </button>
        </form>
    </div>
@endsection