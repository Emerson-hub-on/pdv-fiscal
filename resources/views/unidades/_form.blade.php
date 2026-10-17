@php
    $ehEdicao = $unidade->exists;
    $inputCls = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-800 outline-none transition disabled:bg-gray-100 disabled:cursor-not-allowed';
    $labelCls = 'block text-sm font-medium text-gray-700 mb-1';
    $erroCls  = 'text-red-600 text-xs mt-1';
@endphp

<form method="POST"
      action="{{ $ehEdicao ? route('unidades.update', $unidade) : route('unidades.store') }}"
      class="bg-white rounded-lg shadow p-6 w-full max-w-xl mt-2 mb-6 flex flex-col gap-4">
    @csrf
    @if ($ehEdicao) @method('PUT') @endif

    <div class="flex items-center gap-3">
        <a href="{{ route('unidades.index') }}" title="Voltar" class="text-gray-500 hover:text-gray-800 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </a>
        <h1 class="text-lg font-semibold">{{ $ehEdicao ? 'Editar unidade ' . $unidade->sigla : 'Nova unidade' }}</h1>
    </div>

    <div class="grid grid-cols-3 gap-4">
        <div>
            <label class="{{ $labelCls }}">Sigla <span class="text-red-500">*</span></label>
            <input type="text" name="sigla" maxlength="6" value="{{ old('sigla', $unidade->sigla) }}"
                   {{ $ehEdicao ? 'disabled' : 'required' }}
                   class="{{ $inputCls }} uppercase" style="text-transform: uppercase">
            @error('sigla') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
            @if ($ehEdicao)
                <p class="text-xs text-gray-400 mt-1">A sigla não pode ser alterada.</p>
            @endif
        </div>
        <div class="col-span-2">
            <label class="{{ $labelCls }}">Descrição <span class="text-red-500">*</span></label>
            <input type="text" name="descricao" maxlength="60" value="{{ old('descricao', $unidade->descricao) }}" required
                   class="{{ $inputCls }}">
            @error('descricao') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
            <input type="hidden" name="permite_fracionado" value="0">
            <input type="checkbox" name="permite_fracionado" value="1" @checked(old('permite_fracionado', $unidade->permite_fracionado))
                   class="rounded border-gray-300">
            Permite quantidade fracionada
        </label>
        <p class="text-xs text-gray-500 mt-1">
            Marcado: produtos com esta unidade aceitam estoque com casas decimais (ex.: 2,5 KG).
            Desmarcado: apenas números inteiros.
        </p>
        @error('permite_fracionado') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
    </div>

    <div class="flex gap-3">
        <button type="submit" class="bg-gray-700 text-amber-50 rounded-lg px-6 py-2.5 text-sm hover:bg-gray-500">Salvar</button>
        <a href="{{ route('unidades.index') }}" class="border border-gray-300 rounded-lg px-6 py-2.5 text-sm hover:bg-gray-50">Cancelar</a>
    </div>
</form>
