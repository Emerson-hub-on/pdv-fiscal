@php
    extract(config('estilo.formulario'));
    $sistema = (bool) $forma->sistema;
    $meios = \App\Support\FormasCaixa::MEIOS + ($sistema ? ['05' => 'Crédito Loja (convênio)', '21' => 'Crédito em Loja (troca)'] : []);
    $meioAtual = old('meio_pagamento', $forma->meio_pagamento);
@endphp

<div class="{{ $cartaoCls }} p-6">
    <x-form.secao titulo="Forma de pagamento" icone="cartao">
        Como a opção aparece na tela de Pagamento do caixa e o meio de pagamento informado na NFC-e.
    </x-form.secao>

    @if ($sistema)
        <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-4 py-3 text-sm text-slate-600 mb-5">
            Forma <strong>do sistema</strong>: o caixa depende do meio de pagamento e do comportamento dela
            @if (in_array($forma->tipo, \App\Support\FormasCaixa::TIPOS_ESPECIAIS, true))
                (abre o modal {{ $forma->tipo === 'convenio' ? 'do convênio' : 'do crédito em loja' }})
            @endif
            — aqui dá para mudar o nome, a ordem e a situação.
        </div>
    @endif

    <div class="grid grid-cols-2 gap-5">
        <div class="col-span-2">
            <label class="{{ $labelCls }}">Descrição no caixa <span class="text-red-500">*</span></label>
            <input type="text" name="descricao" maxlength="60" required value="{{ old('descricao', $forma->descricao) }}"
                   placeholder="Ex.: Vale Alimentação, Boleto, Crediário..." class="{{ $inputCls }}">
            @error('descricao') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="{{ $labelCls }}">Meio de pagamento (NFC-e) <span class="text-red-500">*</span></label>
            <select name="meio_pagamento" class="{{ $inputCls }}" @disabled($sistema) onchange="sugerirTroco(this.value)">
                @foreach ($meios as $codigo => $rotulo)
                    <option value="{{ $codigo }}" @selected($meioAtual === (string) $codigo)>{{ $codigo }} — {{ $rotulo }}</option>
                @endforeach
            </select>
            <p class="text-xs text-slate-400 mt-1">Código tPag da SEFAZ. Cartões (03, 04) e PIX (17) entram no registro 1601 do SPED.</p>
            @error('meio_pagamento') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="{{ $labelCls }}">Ordem no caixa</label>
            <input type="number" name="ordem" min="0" max="9999" value="{{ old('ordem', $forma->ordem) }}" class="{{ $inputCls }}">
            <p class="text-xs text-slate-400 mt-1">Menor número aparece primeiro na lista do caixa.</p>
            @error('ordem') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
        </div>

        <div class="col-span-2 flex flex-col gap-3">
            <label class="inline-flex items-center gap-3 cursor-pointer select-none {{ $sistema ? 'opacity-60 cursor-not-allowed' : '' }}">
                <input type="hidden" name="permite_troco" value="0">
                <span class="relative inline-flex items-center">
                    <input type="checkbox" name="permite_troco" value="1" id="permite-troco" class="peer sr-only"
                           @checked(old('permite_troco', $forma->permite_troco)) @disabled($sistema)>
                    <span class="w-11 h-6 rounded-full bg-slate-300 transition peer-checked:bg-[#10284A]"></span>
                    <span class="absolute left-0.5 top-0.5 w-5 h-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                </span>
                <span>
                    <span class="block text-sm font-semibold text-slate-800">Aceita troco</span>
                    <span class="block text-xs text-slate-500">O cliente pode entregar mais que o valor e receber troco (normalmente só dinheiro).</span>
                </span>
            </label>

            <label class="inline-flex items-center gap-3 cursor-pointer select-none">
                <input type="hidden" name="ativo" value="0">
                <span class="relative inline-flex items-center">
                    <input type="checkbox" name="ativo" value="1" class="peer sr-only" @checked(old('ativo', $forma->ativo))>
                    <span class="w-11 h-6 rounded-full bg-slate-300 transition peer-checked:bg-[#10284A]"></span>
                    <span class="absolute left-0.5 top-0.5 w-5 h-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                </span>
                <span>
                    <span class="block text-sm font-semibold text-slate-800">Ativa</span>
                    <span class="block text-xs text-slate-500">Inativa não aparece no caixa (as vendas antigas continuam com ela).</span>
                </span>
            </label>
            @error('ativo') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="flex flex-row-reverse gap-3 mt-6 pt-5 border-t border-slate-100">
        <button type="submit" class="{{ $botaoPrimarioCls }}">
            <x-form.icone nome="check" /> Salvar
        </button>
        <a href="{{ route('formas-pagamento-caixa.index') }}" class="{{ $botaoCancelarCls }}">Cancelar</a>
    </div>
</div>

@unless ($sistema)
    <script>
        // Dinheiro (01) normalmente dá troco: sugere ligar ao escolher esse meio
        function sugerirTroco(meio) {
            if (meio === '01') document.getElementById('permite-troco').checked = true;
        }
    </script>
@endunless
