@php
    $ehEdicao = isset($cfopSaida) && $cfopSaida !== null;
    extract(config('estilo.formulario'));
@endphp

<form method="POST" action="{{ $ehEdicao ? route('cfop-saida.update', $cfopSaida) : route('cfop-saida.store') }}"
      class="{{ $cartaoCls }} max-w-xl">
    @csrf
    @if ($ehEdicao) @method('PUT') @endif

    <x-form.cabecalho rotulo="Cadastros · CFOP de saída" :titulo="$ehEdicao ? 'Editar CFOP' : 'Novo CFOP de Saída'" />

    <div class="p-6 flex flex-col gap-4">
    <div>
        <label class="{{ $labelCls }}">Código CFOP</label>
        <input type="text" name="codigo" maxlength="4" placeholder="5102" required
               value="{{ old('codigo', $ehEdicao ? $cfopSaida->codigo : '') }}"
               class="{{ $inputCls }}">
        @error('codigo') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="{{ $labelCls }}">Descrição</label>
        <input type="text" name="descricao" required placeholder="Ex: Venda de mercadoria adquirida ou recebida de terceiros"
               value="{{ old('descricao', $ehEdicao ? $cfopSaida->descricao : '') }}"
               class="{{ $inputCls }}">
        @error('descricao') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="{{ $labelCls }}">Finalidade</label>
        <input type="text" name="finalidade" placeholder="Ex: Venda, Devolução, Remessa, Transferência, Bonificação"
               value="{{ old('finalidade', $ehEdicao ? $cfopSaida->finalidade : '') }}"
               class="{{ $inputCls }}">
    </div>

    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" name="movimenta_estoque" value="1" class="accent-[#10284A]"
               @checked(old('movimenta_estoque', $ehEdicao ? $cfopSaida->movimenta_estoque : true))>
        Movimenta estoque
    </label>


    <button type="submit" class="{{ $botaoPrimarioCls }} self-end">
        <x-form.icone nome="check" />
        {{ $ehEdicao ? 'Salvar Alterações' : 'Cadastrar' }}
    </button>
    </div>
</form>