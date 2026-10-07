@extends('layouts.app')

@section('titulo', 'Abrir Caixa')

@section('conteudo')
    <div class="max-w-md mx-auto bg-white p-6 rounded shadow mt-10">
        <h1 class="text-xl font-bold mb-6">Abertura de Caixa</h1>

        <form action="{{ route('caixa.abrir') }}" method="POST">
            @csrf

            @php
                $livres = $pdvs->whereNotIn('id', $ocupados);
                $selecionado = old('pdv_id', $livres->count() === 1 ? $livres->first()->id : null);
            @endphp

            <label class="block text-sm font-medium mb-1">PDV</label>
            <select name="pdv_id" required class="w-full border rounded px-3 py-2 mb-1">
                <option value="" disabled @selected(!$selecionado)>Selecione o PDV</option>
                @foreach ($pdvs as $pdv)
                    @php $emUso = in_array($pdv->id, $ocupados); @endphp
                    <option value="{{ $pdv->id }}"
                            @selected((string) $selecionado === (string) $pdv->id)
                            @disabled($emUso)>
                        {{ $pdv->nome }}{{ $emUso ? ' (caixa já aberto)' : '' }}
                    </option>
                @endforeach
            </select>
            @error('pdv_id')
                <p class="text-sm text-red-600 mb-3">{{ $message }}</p>
            @enderror
            <div class="mb-3"></div>

            <label class="block text-sm font-medium mb-1">Valor inicial em caixa (troco)</label>
            <input type="number" step="0.01" name="valor_abertura" required
                   value="{{ old('valor_abertura') }}"
                   class="w-full border rounded px-3 py-2 mb-4">

            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded font-semibold">
                Abrir Caixa
            </button>
        </form>
    </div>
@endsection