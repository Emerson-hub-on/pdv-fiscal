@php $condutor = $condutor ?? null; @endphp

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

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <div class="grid grid-cols-2 gap-5">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nome completo <span class="text-red-500">*</span></label>
            <input type="text" name="nome" maxlength="60" required
                   value="{{ old('nome', $condutor->nome ?? '') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">CPF <span class="text-red-500">*</span></label>
            <input type="text" name="cpf" id="cpf-condutor" maxlength="14" required inputmode="numeric" placeholder="000.000.000-00"
                   value="{{ old('cpf', $condutor->cpf_formatado ?? '') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
        </div>

    </div>
</div>

<div class="mt-6 flex gap-3">
    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">
        Salvar condutor
    </button>
    <a href="{{ route('condutores.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg text-sm font-medium transition">
        Cancelar
    </a>
</div>

<script>
    document.getElementById('cpf-condutor').addEventListener('input', (e) => {
        const d = e.target.value.replace(/\D/g, '').slice(0, 11);
        e.target.value = d
            .replace(/^(\d{3})(\d)/, '$1.$2')
            .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
    });
</script>
