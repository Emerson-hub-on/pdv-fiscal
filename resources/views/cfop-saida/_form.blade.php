@php $ehEdicao = isset($cfopSaida) && $cfopSaida !== null; @endphp

<form method="POST" action="{{ $ehEdicao ? route('cfop-saida.update', $cfopSaida) : route('cfop-saida.store') }}"
      class="bg-white rounded-lg shadow p-6 max-w-xl flex flex-col gap-4">
    @csrf
    @if ($ehEdicao) @method('PUT') @endif

    <h1 class="text-lg font-semibold">{{ $ehEdicao ? 'Editar CFOP' : 'Novo CFOP de Saída' }}</h1>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Código CFOP</label>
        <input type="text" name="codigo" maxlength="4" placeholder="5102" required
               value="{{ old('codigo', $ehEdicao ? $cfopSaida->codigo : '') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        @error('codigo') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
        <input type="text" name="descricao" required placeholder="Ex: Venda de mercadoria adquirida ou recebida de terceiros"
               value="{{ old('descricao', $ehEdicao ? $cfopSaida->descricao : '') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        @error('descricao') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Finalidade</label>
        <input type="text" name="finalidade" placeholder="Ex: Venda, Devolução, Remessa, Transferência, Bonificação"
               value="{{ old('finalidade', $ehEdicao ? $cfopSaida->finalidade : '') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </div>

    <label class="flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="movimenta_estoque" value="1"
               @checked(old('movimenta_estoque', $ehEdicao ? $cfopSaida->movimenta_estoque : true))>
        Movimenta estoque
    </label>

    <button type="submit" class="bg-gray-800 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-gray-700 w-fit">
        {{ $ehEdicao ? 'Salvar Alterações' : 'Cadastrar' }}
    </button>
</form>