
@if ($errors->any())
    <div class="bg-red-100 text-red-700 border border-red-300 rounded px-4 py-3 mb-4 col-span-2">
        <ul class="list-disc list-inside text-sm">
            @foreach ($errors->all() as $erro)
                <li>{{ $erro }}</li>
            @endforeach
        </ul>
    </div>
@endif
<div class="grid grid-cols-2 gap-4">
    <div class="col-span-2">
        <label class="block text-sm font-medium mb-1">Nome *</label>
        <input type="text" name="nome" value="{{ old('nome', $pdv->nome ?? '') }}" required
               class="w-full border rounded px-3 py-2">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Série NFC-e *</label>
        <input type="number" name="serie_nfce" value="{{ old('serie_nfce', $pdv->serie_nfce ?? 1) }}" required
               class="w-full border rounded px-3 py-2">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">
            Número atual da NFC-e *
            <span class="text-xs text-gray-400 font-normal">(a próxima emissão usa este + 1)</span>
        </label>
        <input type="number" name="numero_atual_nfce" value="{{ old('numero_atual_nfce', $pdv->numero_atual_nfce ?? 0) }}" required
               class="w-full border rounded px-3 py-2">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">CSC *</label>
        <input type="text" name="csc" value="{{ old('csc', $pdv->csc ?? '') }}" required
               class="w-full border rounded px-3 py-2">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">CSC ID *</label>
        <input type="text" name="csc_id" value="{{ old('csc_id', $pdv->csc_id ?? '') }}" required
               class="w-full border rounded px-3 py-2">
    </div>
</div>

<div class="mt-6 flex gap-3">
    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-semibold">
        Salvar
    </button>
    <a href="{{ route('pdvs.index') }}" class="bg-gray-200 hover:bg-gray-300 px-6 py-2 rounded">
        Cancelar
    </a>
</div>