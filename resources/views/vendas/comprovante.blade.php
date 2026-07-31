@extends('layouts.app')

@section('titulo', 'Comprovante de Venda')

@section('conteudo')
    <div class="max-w-lg mx-auto bg-white p-6 rounded shadow">
        <h1 class="text-xl font-bold mb-4">Venda #{{ $venda->id }}</h1>

        @if (session('sucesso'))
            <div class="bg-green-100 text-green-800 border border-green-300 rounded px-4 py-3 mb-4 text-sm">
                {{ session('sucesso') }}
            </div>
        @endif

        @if (session('erro_fiscal'))
            <div class="bg-red-100 text-red-700 border border-red-300 rounded px-4 py-3 mb-4 text-sm">
                <strong>Erro na emissão:</strong> {{ session('erro_fiscal') }}
            </div>
        @endif

        <table class="w-full text-sm mb-4">
            @foreach ($venda->itens as $item)
                <tr class="border-b">
                    <td class="py-1">{{ $item->produto->nome }} x{{ $item->quantidade }}</td>
                    <td class="py-1 text-right">R$ {{ number_format($item->subtotal, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </table>

        <p class="text-right font-bold text-lg mb-6">Total: R$ {{ number_format($venda->total, 2, ',', '.') }}</p>

        @if ($venda->status === 'emitida')
            <p class="text-green-700 text-sm mb-2">✓ Cupom fiscal emitido</p>
            <p class="text-xs text-gray-500 break-all">Chave: {{ $venda->chave_nfe }}</p>
        @else
            <form action="{{ route('vendas.emitir', $venda) }}" method="POST">
                @csrf
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded font-bold">
                    Emitir Cupom Fiscal
                </button>
            </form>
        @endif

        <a href="{{ route('vendas.pdv') }}" class="block text-center text-sm text-gray-500 mt-4 hover:underline">
            ← Nova venda
        </a>
    </div>
@endsection