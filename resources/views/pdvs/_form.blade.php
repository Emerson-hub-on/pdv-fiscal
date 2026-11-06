@php extract(config('estilo.formulario')); @endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 grid grid-cols-2 gap-5">

    <div class="col-span-2">
        <label class="{{ $labelCls }}">Nome <span class="text-red-500">*</span></label>
        <input type="text" name="nome" value="{{ old('nome', $pdv->nome ?? '') }}" required
               class="{{ $inputCls }}">
    </div>

    <div class="col-span-2">
        <label class="{{ $labelCls }}">
            Computador (nome)
            <span class="{{ $opcionalCls }}">
                (opcional; restringe este PDV a um único PC. Para descobrir o nome, rode <code>hostname</code> no cmd do computador)
            </span>
        </label>
        <input type="text" name="maquina" value="{{ old('maquina', $pdv->maquina ?? '') }}"
               placeholder="Ex.: CAIXA01-PC" style="text-transform: uppercase"
               class="{{ $inputCls }}">
    </div>

    <div>
        <label class="{{ $labelCls }}">Série NFC-e <span class="text-red-500">*</span></label>
        <input type="number" name="serie_nfce" value="{{ old('serie_nfce', $pdv->serie_nfce ?? 1) }}" required
               class="{{ $inputCls }}">
    </div>

    <div>
        <label class="{{ $labelCls }}">
            Número atual da NFC-e <span class="text-red-500">*</span>
            <span class="{{ $opcionalCls }}">(a próxima emissão usa este + 1)</span>
        </label>
        <input type="number" name="numero_atual_nfce" value="{{ old('numero_atual_nfce', $pdv->numero_atual_nfce ?? 0) }}" required
               class="{{ $inputCls }}">
    </div>

    <div>
        <label class="{{ $labelCls }}">CSC <span class="text-red-500">*</span></label>
        <input type="text" name="csc" value="{{ old('csc', $pdv->csc ?? '') }}" required
               class="{{ $inputCls }}">
    </div>

    <div>
        <label class="{{ $labelCls }}">CSC ID <span class="text-red-500">*</span></label>
        <input type="text" name="csc_id" value="{{ old('csc_id', $pdv->csc_id ?? '') }}" required
               class="{{ $inputCls }}">
    </div>
</div>

<div class="mt-6 flex flex-row-reverse justify-start gap-3">
    <button type="submit"
            class="{{ $botaoPrimarioCls }}">
        Salvar
    </button>
    <a href="{{ route('pdvs.index') }}"
       class="{{ $botaoCancelarCls }}">
        Cancelar
    </a>
</div>