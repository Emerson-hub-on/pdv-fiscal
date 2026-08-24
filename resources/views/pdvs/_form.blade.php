@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
        <p class="font-semibold mb-1">Corrija os erros abaixo:</p>
        <ul class="list-disc pl-5 space-y-0.5">
            @foreach ($errors->all() as $erro)
                <li>{{ $erro }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 grid grid-cols-2 gap-5">

    <div class="col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Nome <span class="text-red-500">*</span></label>
        <input type="text" name="nome" value="{{ old('nome', $pdv->nome ?? '') }}" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Série NFC-e <span class="text-red-500">*</span></label>
        <input type="number" name="serie_nfce" value="{{ old('serie_nfce', $pdv->serie_nfce ?? 1) }}" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
            Número atual da NFC-e <span class="text-red-500">*</span>
            <span class="text-xs text-gray-400 font-normal">(a próxima emissão usa este + 1)</span>
        </label>
        <input type="number" name="numero_atual_nfce" value="{{ old('numero_atual_nfce', $pdv->numero_atual_nfce ?? 0) }}" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">CSC <span class="text-red-500">*</span></label>
        <input type="text" name="csc" value="{{ old('csc', $pdv->csc ?? '') }}" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">CSC ID <span class="text-red-500">*</span></label>
        <input type="text" name="csc_id" value="{{ old('csc_id', $pdv->csc_id ?? '') }}" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
    </div>
</div>

<div class="mt-6 flex gap-3">
    <button type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">
        Salvar
    </button>
    <a href="{{ route('pdvs.index') }}"
       class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg text-sm font-medium transition">
        Cancelar
    </a>
</div>