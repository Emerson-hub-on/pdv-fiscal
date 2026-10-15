@extends('layouts.app')

@section('titulo', 'Pedidos de Compra')

@section('conteudo')
<div class="bg-white rounded-lg shadow overflow-visible">
    @if (session('erro'))
        <div role="alert" class="m-4 mb-0 border border-red-300 bg-red-50 text-red-800 rounded-lg px-4 py-3 text-sm whitespace-pre-line">{{ session('erro') }}</div>
    @endif

    <form method="GET" action="{{ route('pedidos-compra.index') }}"
          class="flex flex-col gap-3 p-4 border-b border-gray-100">

        <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-lg font-semibold mr-1">Pedidos de Compra</h1>

            <input type="text" name="busca" placeholder="Nº do pedido" value="{{ request('busca') }}"
                   class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-40">

            <button type="submit"
                    class="bg-gray-800 text-white rounded-lg px-3 py-1.5 text-sm hover:bg-gray-700">
                Buscar
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <select name="status" onchange="this.form.submit()"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Todos os status</option>
                <option value="aberto" @selected(request('status') === 'aberto')>Em aberto</option>
                <option value="enviado" @selected(request('status') === 'enviado')>Enviados</option>
                <option value="recebido" @selected(request('status') === 'recebido')>Recebidos</option>
                <option value="cancelado" @selected(request('status') === 'cancelado')>Cancelados</option>
            </select>

            <label class="flex items-center gap-1 text-sm text-gray-600">
                Data de
                <input type="date" name="data_de" value="{{ request('data_de') }}" onchange="this.form.submit()"
                       class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
            </label>
            <label class="flex items-center gap-1 text-sm text-gray-600">
                até
                <input type="date" name="data_ate" value="{{ request('data_ate') }}" onchange="this.form.submit()"
                       class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
            </label>

            @if (request()->hasAny(['busca', 'status', 'data_de', 'data_ate']))
                <a href="{{ route('pedidos-compra.index') }}" class="text-sm text-gray-500 hover:underline">Limpar filtros</a>
            @endif

            <a href="{{ route('pedidos-compra.create') }}"
               class="bg-gray-700 text-amber-50 rounded-lg px-4 py-2 text-sm hover:bg-gray-500">+ Novo pedido de compra</a>
        </div>
    </form>

    @include('financeiro.pedidos-compra._tabela', ['pedidos' => $pedidos])
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