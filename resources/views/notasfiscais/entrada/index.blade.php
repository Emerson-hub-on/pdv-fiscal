@extends('layouts.app')

@section('titulo', 'Nota Fiscal - Entrada')

@section('conteudo')
<div class="bg-white rounded-lg shadow overflow-visible">
    <form method="GET" action="{{ route('entradas-nota.index') }}"
          class="flex flex-col gap-3 p-4 border-b border-gray-100">

        <!-- Linha 1: título + busca -->
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-lg font-semibold mr-1">Notas Fiscais de Entrada</h1>

            <input type="text" name="busca" placeholder="Fornecedor, número ou chave de acesso"
                   value="{{ request('busca') }}"
                   class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-72">

            <button type="submit"
                    class="bg-gray-800 text-white rounded-lg px-3 py-1.5 text-sm hover:bg-gray-700">
                Buscar
            </button>
        </div>

        <!-- Linha 2: status + ações -->
        <div class="flex flex-wrap items-center gap-2">
            <select name="status" onchange="this.form.submit()"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Todos os status</option>
                <option value="rascunho" @selected(request('status') === 'rascunho')>Rascunho</option>
                <option value="finalizada" @selected(request('status') === 'finalizada')>Finalizada</option>
            </select>

            <a href="{{ route('entradas-nota.create') }}"
               class="bg-gray-700 text-amber-50 rounded-lg px-4 py-2 text-sm hover:bg-gray-500">+Nova entrada</a>
        </div>
    </form>

    @include('notasfiscais.entrada._tabela', ['entradas' => $entradas])
</div>
@endsection

@section('scripts')
<script>
function toggleAcoesLinha(id) {
    document.querySelectorAll('[id^="dropdown-acoes-"]').forEach(el => {
        if (el.id !== `dropdown-acoes-${id}`) el.classList.add('hidden');
    });
    document.getElementById(`dropdown-acoes-${id}`).classList.toggle('hidden');
}

document.addEventListener('click', (e) => {
    document.querySelectorAll('[id^="dropdown-acoes-"]').forEach(dropdown => {
        const container = dropdown.closest('td');
        if (!dropdown.classList.contains('hidden') && container && !container.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
});
</script>
@endsection
