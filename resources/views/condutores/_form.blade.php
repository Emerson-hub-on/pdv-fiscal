@php extract(config('estilo.formulario')); @endphp
@php $condutor = $condutor ?? null; @endphp

<div class="{{ $cartaoCls }} p-6">
    <div class="grid grid-cols-2 gap-5">

        <div>
            <label class="{{ $labelCls }}">Nome completo <span class="text-red-500">*</span></label>
            <input type="text" name="nome" maxlength="60" required
                   value="{{ old('nome', $condutor->nome ?? '') }}"
                   class="{{ $inputCls }}">
        </div>

        <div>
            <label class="{{ $labelCls }}">CPF <span class="text-red-500">*</span></label>
            <input type="text" name="cpf" id="cpf-condutor" maxlength="14" required inputmode="numeric" placeholder="000.000.000-00"
                   value="{{ old('cpf', $condutor->cpf_formatado ?? '') }}"
                   class="{{ $inputCls }} font-mono">
        </div>

    </div>
</div>

<div class="mt-6 flex flex-row-reverse justify-start gap-3">
    <button type="submit" class="{{ $botaoPrimarioCls }}">
        Salvar condutor
    </button>
    <a href="{{ route('condutores.index') }}" class="{{ $botaoCancelarCls }}">
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
