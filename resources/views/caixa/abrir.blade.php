@extends('layouts.caixa')

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

    @if ($caixasDeOutros->isNotEmpty())
        <div class="max-w-md mx-auto bg-white p-6 rounded shadow mt-4 mb-10">
            <h2 class="text-sm font-bold text-gray-800">Caixas abertos neste computador</h2>
            <p class="text-xs text-gray-500 mt-1 mb-3">
                O operador saiu sem fechar? Um supervisor ou administrador pode fechar o caixa dele para liberar o PDV.
            </p>
            <ul class="divide-y divide-gray-100 border border-gray-200 rounded">
                @foreach ($caixasDeOutros as $aberto)
                    <li class="flex items-center justify-between gap-3 px-3 py-2">
                        <div class="text-sm min-w-0">
                            <p class="font-medium text-gray-800 truncate">{{ $aberto->pdv->nome ?? 'PDV #' . $aberto->pdv_id }}</p>
                            <p class="text-xs text-gray-500 truncate">
                                {{ $aberto->operador_nome }} · aberto em {{ $aberto->data_abertura?->format('d/m/Y H:i') }}
                            </p>
                        </div>
                        <a href="{{ route('caixa.fechar-outro-form', $aberto->uuid) }}"
                           class="shrink-0 px-3 py-1.5 text-xs font-semibold text-white bg-slate-700 hover:bg-slate-800 rounded transition">
                            Fechar (supervisor)
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection