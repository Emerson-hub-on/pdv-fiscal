@extends('layouts.app')

@section('titulo', 'Nova Nota Fiscal')

@section('conteudo')
<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <h1 class="text-lg font-semibold mb-4">Nova Nota Fiscal (Saída)</h1>

    <form method="POST" action="{{ route('notasfiscais.store') }}" class="flex flex-col gap-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
            <select name="cliente_id" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Selecione...</option>
                @foreach (\App\Models\Cliente::ativos()->orderBy('nome')->get() as $cliente)
                    <option value="{{ $cliente->id }}">{{ $cliente->nome }} — {{ $cliente->cpf_cnpj_formatado }}</option>
                @endforeach
            </select>
            @error('cliente_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Natureza da operação</label>
            <input type="text" name="natureza_operacao" required placeholder="Ex: Venda de mercadoria"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Finalidade</label>
            <select name="finalidade" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="1">Normal</option>
                <option value="2">Complementar</option>
                <option value="3">Ajuste</option>
                <option value="4">Devolução</option>
            </select>
        </div>

        <button type="submit"
                class="bg-gray-800 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-gray-700 w-fit">
            Criar e adicionar itens
        </button>
    </form>
</div>
@endsection