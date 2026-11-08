@php
    extract(config('estilo.formulario'));
    $s = $mesa->dadosStatus();
    $livre = $mesa->status === \App\Models\Mesa::DISPONIVEL;
@endphp

<div class="{{ $cartaoCls }} p-6">
    <x-form.secao titulo="Mesa" icone="loja">
        A mesa aparece para o garçom na aba Mesas. A situação muda sozinha: "Aguardando pedido" quando chega pedido, "Pedido entregue" quando todos os pedidos foram entregues e "Disponível" ao fechar a conta.
    </x-form.secao>

    @if ($mesa->exists)
        <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-4 py-3 mb-5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Situação agora</span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $s['selo'] }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $s['ponto'] }}"></span> {{ $s['rotulo'] }}
            </span>
            @if (!$livre && $mesa->status_desde)
                <span class="text-slate-500">
                    desde {{ $mesa->status_desde->format('d/m H:i') }}
                    (<span data-desde="{{ $mesa->status_desde->toIso8601String() }}"></span>)
                    @if ($mesa->alteradoPor) · {{ $mesa->alteradoPor->name }} @endif
                </span>
            @endif
        </div>
    @endif

    <div class="grid grid-cols-3 gap-5">
        <div>
            <label class="{{ $labelCls }}">Número <span class="text-red-500">*</span></label>
            <input type="number" name="numero" min="1" max="9999" required value="{{ old('numero', $mesa->numero) }}" class="{{ $inputCls }}">
            @error('numero') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="{{ $labelCls }}">Nome <span class="{{ $opcionalCls }}">(opcional)</span></label>
            <input type="text" name="nome" maxlength="60" value="{{ old('nome', $mesa->nome) }}" placeholder="Ex.: Varanda, Salão 2" class="{{ $inputCls }}">
            @error('nome') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="{{ $labelCls }}">Lugares <span class="{{ $opcionalCls }}">(opcional)</span></label>
            <input type="number" name="lugares" min="1" max="99" value="{{ old('lugares', $mesa->lugares) }}" placeholder="Ex.: 4" class="{{ $inputCls }}">
            @error('lugares') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
        </div>

        <div class="col-span-3">
            <label class="inline-flex items-center gap-3 cursor-pointer select-none">
                <input type="hidden" name="ativo" value="0">
                <span class="relative inline-flex items-center">
                    <input type="checkbox" name="ativo" value="1" class="peer sr-only" @checked(old('ativo', $mesa->ativo))>
                    <span class="w-11 h-6 rounded-full bg-slate-300 transition peer-checked:bg-[#10284A]"></span>
                    <span class="absolute left-0.5 top-0.5 w-5 h-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                </span>
                <span>
                    <span class="block text-sm font-semibold text-slate-800">Ativa</span>
                    <span class="block text-xs text-slate-500">Inativa não aparece para o garçom (só dá para inativar com a mesa disponível).</span>
                </span>
            </label>
            @error('ativo') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="flex items-center gap-3 mt-6 pt-5 border-t border-slate-100">
        @if ($mesa->exists)
            @if ($livre)
                <button type="submit" form="form-excluir-mesa"
                        class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-medium text-red-600 ring-1 ring-red-200 hover:bg-red-50 transition">
                    Excluir
                </button>
            @else
                <span class="text-xs text-slate-400">Mesa em atendimento: só pode ser excluída quando estiver disponível.</span>
            @endif
        @endif

        <div class="ml-auto flex flex-row-reverse gap-3">
            <button type="submit" class="{{ $botaoPrimarioCls }}">
                <x-form.icone nome="check" /> Salvar
            </button>
            <a href="{{ route('mesas.index') }}" class="{{ $botaoCancelarCls }}">Cancelar</a>
        </div>
    </div>
</div>

@include('comandas.mesas._relogio')
