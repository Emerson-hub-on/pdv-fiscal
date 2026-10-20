@extends('layouts.app')

@section('titulo', 'Inventário de Produtos')

{{-- Libera o max-w-5xl do layout para a tabela usar a largura toda da tela --}}
@section('body-class', 'conteudo-largo')

@section('breadcrumb')
    <span>Cadastros</span>
    <span class="text-gray-300">›</span>
    <span>Produtos</span>
    <span class="text-gray-300">›</span>
    <span class="text-gray-700 font-medium">Inventário</span>
@endsection

@section('conteudo')
    <div class="flex justify-end items-center gap-2 mb-6">
        <button type="button" onclick="exportarInventario('excel')"
                class="bg-green-600 hover:bg-green-500 text-white px-4 py-2.5 rounded-lg font-semibold text-sm transition">
            Exportar Excel
        </button>
        <button type="button" onclick="exportarInventario('pdf')"
                class="bg-red-600 hover:bg-red-500 text-white px-4 py-2.5 rounded-lg font-semibold text-sm transition">
            Exportar PDF
        </button>
    </div>

    <form id="form-inventario" class="flex flex-wrap items-center gap-2 mb-4">
        <select name="tipo_busca" data-filtro
                class="bg-white border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-sm font-medium outline-none focus:ring-2 focus:ring-slate-800">
            <option value="nome" @selected($filtros['tipo_busca'] === 'nome')>Descrição</option>
            <option value="codigo_interno" @selected($filtros['tipo_busca'] === 'codigo_interno')>Código interno</option>
            <option value="codigo_barras" @selected($filtros['tipo_busca'] === 'codigo_barras')>Código de barras</option>
            <option value="referencia" @selected($filtros['tipo_busca'] === 'referencia')>Referência</option>
        </select>

        <input type="text" name="busca" id="busca-input" value="{{ $filtros['busca'] }}" placeholder="Buscar..." autocomplete="off"
               class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm w-64 focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">

        <select name="categoria" data-filtro
                class="bg-white border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-sm font-medium outline-none focus:ring-2 focus:ring-slate-800">
            <option value="">Todas as categorias</option>
            @foreach ($categorias as $categoria)
                <option value="{{ $categoria->id }}" @selected((string) $filtros['categoria'] === (string) $categoria->id)>{{ $categoria->nome }}</option>
            @endforeach
        </select>

        <select name="situacao" data-filtro
                class="bg-white border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-sm font-medium outline-none focus:ring-2 focus:ring-slate-800">
            <option value="todos" @selected($filtros['situacao'] === 'todos')>Situação: Todas</option>
            <option value="repor" @selected($filtros['situacao'] === 'repor')>Abaixo do mínimo</option>
            <option value="zerado" @selected($filtros['situacao'] === 'zerado')>Zerados</option>
            <option value="negativo" @selected($filtros['situacao'] === 'negativo')>Negativos</option>
            <option value="com_estoque" @selected($filtros['situacao'] === 'com_estoque')>Com estoque</option>
        </select>

        <div class="flex items-center gap-2">
            <select name="campo_data" data-filtro
                    class="bg-white border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-sm font-medium outline-none focus:ring-2 focus:ring-slate-800">
                <option value="cadastro" @selected($filtros['campo_data'] === 'cadastro')>Data de cadastro</option>
                <option value="alteracao" @selected($filtros['campo_data'] === 'alteracao')>Última alteração</option>
            </select>
            <input type="date" name="data_de" data-filtro value="{{ $filtros['data_de'] }}" title="De"
                   class="bg-white border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
            <span class="text-sm text-gray-400">até</span>
            <input type="date" name="data_ate" data-filtro value="{{ $filtros['data_ate'] }}" title="Até"
                   class="bg-white border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
        </div>

        <div class="flex items-center gap-2 ml-auto">
            <select name="status" data-filtro
                    class="bg-white border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-sm font-medium outline-none focus:ring-2 focus:ring-slate-800">
                <option value="ativos" @selected($filtros['status'] === 'ativos')>Status: Ativos</option>
                <option value="inativos" @selected($filtros['status'] === 'inativos')>Status: Inativos</option>
                <option value="todos" @selected($filtros['status'] === 'todos')>Status: Todos</option>
            </select>

            <select name="ordenar" data-filtro
                    class="bg-white border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-sm font-medium outline-none focus:ring-2 focus:ring-slate-800">
                <option value="nome" @selected($filtros['ordenar'] === 'nome')>Ordenar: Alfabética</option>
                <option value="codigo" @selected($filtros['ordenar'] === 'codigo')>Ordenar: Código</option>
                <option value="estoque" @selected($filtros['ordenar'] === 'estoque')>Ordenar: Menor estoque</option>
            </select>
        </div>
    </form>

    <div id="resultado-inventario">
        @include('produtos.inventario._tabela', ['itens' => $itens, 'totais' => $totais])
    </div>

    <script>
        let timeoutBuscaInventario;
        const formInventario = document.getElementById('form-inventario');

        function parametrosInventario() {
            return new URLSearchParams(new FormData(formInventario));
        }

        async function buscarInventario() {
            const url = `{{ route('produtos.inventario.index') }}?${parametrosInventario().toString()}`;

            const resp = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            document.getElementById('resultado-inventario').innerHTML = await resp.text();
            window.history.replaceState(null, '', url);
        }

        const rotasExportarInventario = {
            excel: `{{ route('produtos.inventario.exportar-excel') }}`,
            pdf: `{{ route('produtos.inventario.exportar-pdf') }}`,
        };

        // Exporta todos os itens do filtro atual (não só a página que está na tela)
        function exportarInventario(formato) {
            window.location.href = `${rotasExportarInventario[formato]}?${parametrosInventario().toString()}`;
        }

        document.querySelectorAll('[data-filtro]').forEach((el) => {
            el.addEventListener('change', buscarInventario);
        });

        document.getElementById('busca-input').addEventListener('input', () => {
            clearTimeout(timeoutBuscaInventario);
            timeoutBuscaInventario = setTimeout(buscarInventario, 300);
        });

        formInventario.addEventListener('submit', (e) => {
            e.preventDefault();
            buscarInventario();
        });
    </script>
@endsection