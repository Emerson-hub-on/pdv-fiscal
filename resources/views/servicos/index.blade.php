@extends('layouts.app')

@section('titulo', 'Serviços')

@section('conteudo')
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Serviços</h1>
        <a href="{{ route('servicos.create') }}"
           class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2.5 rounded-lg font-semibold text-sm transition">
            + Novo serviço
        </a>
    </div>

    <form id="form-busca-servico" class="mb-4 flex flex-wrap items-center gap-2">
        <input type="text" id="busca-input" value="{{ request('busca') }}" placeholder="Buscar por nome, código ou código de tributação..." autocomplete="off"
               class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm w-96 focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">

        <select id="status-input"
                class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
            <option value="">Todos</option>
            <option value="ativos" @selected(request('status') === 'ativos')>Ativos</option>
            <option value="inativos" @selected(request('status') === 'inativos')>Inativos</option>
        </select>
    </form>

    <div id="resultado-servicos">
        @include('servicos._tabela')
    </div>

    <script>
        let timeoutBuscaServico;

        async function buscarServicos() {
            const params = new URLSearchParams({
                busca:  document.getElementById('busca-input').value,
                status: document.getElementById('status-input').value,
            });
            const url = `{{ route('servicos.index') }}?${params}`;
            const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            document.getElementById('resultado-servicos').innerHTML = await resp.text();
            window.history.replaceState(null, '', url);
        }

        document.getElementById('busca-input').addEventListener('input', () => {
            clearTimeout(timeoutBuscaServico);
            timeoutBuscaServico = setTimeout(buscarServicos, 300);
        });

        document.getElementById('status-input').addEventListener('change', buscarServicos);

        document.getElementById('form-busca-servico').addEventListener('submit', (e) => {
            e.preventDefault();
            buscarServicos();
        });
    </script>
@endsection
