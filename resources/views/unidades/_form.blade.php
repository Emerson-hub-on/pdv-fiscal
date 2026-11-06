@php
    $ehEdicao = $unidade->exists;
    extract(config('estilo.formulario'));
@endphp

<form method="POST"
      action="{{ $ehEdicao ? route('unidades.update', $unidade) : route('unidades.store') }}"
      class="{{ $cartaoCls }} w-full max-w-xl mt-2 mb-6">
    @csrf
    @if ($ehEdicao) @method('PUT') @endif

    <x-form.cabecalho rotulo="Cadastros · Unidades" :titulo="$ehEdicao ? 'Editar unidade ' . $unidade->sigla : 'Nova unidade'"
                      :voltar="route('unidades.index')" voltar-titulo="Voltar para a lista" />

    <div class="p-6 flex flex-col gap-4">
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
                   class="rounded border-slate-300 accent-[#10284A]">
            Permite quantidade fracionada
        </label>
        <p class="text-xs text-gray-500 mt-1">
            Marcado: produtos com esta unidade aceitam estoque com casas decimais (ex.: 2,5 KG).
            Desmarcado: apenas números inteiros.
        </p>
        @error('permite_fracionado') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
    </div>

    <div class="flex flex-row-reverse gap-3 pt-4 border-t border-slate-100">
        <button type="submit" class="{{ $botaoPrimarioCls }}"><x-form.icone nome="check" /> Salvar</button>
        <a href="{{ route('unidades.index') }}" class="{{ $botaoCancelarCls }}">Cancelar</a>
    </div>
    </div>
</form>
