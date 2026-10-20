@extends('layouts.app')

@section('titulo', 'Estoque Baixo')

{{-- Libera o max-w-5xl do layout para a tabela usar a largura toda da tela --}}
@section('body-class', 'conteudo-largo')

@section('breadcrumb')
    <span>Cadastros</span>
    <span class="text-gray-300">›</span>
    <span>Produtos</span>
    <span class="text-gray-300">›</span>
    <span class="text-gray-700 font-medium">Estoque Baixo</span>
@endsection

@section('conteudo')
    <div class="flex justify-end items-center gap-2 mb-6">
        <button type="button" onclick="exportarEstoqueBaixo('excel')"
                class="bg-green-600 hover:bg-green-500 text-white px-4 py-2.5 rounded-lg font-semibold text-sm transition">
            Exportar Excel
        </button>
        <button type="button" onclick="exportarEstoqueBaixo('pdf')"
                class="bg-red-600 hover:bg-red-500 text-white px-4 py-2.5 rounded-lg font-semibold text-sm transition">
            Exportar PDF
        </button>
    </div>

    <form id="form-estoque-baixo" class="mb-4 space-y-2">
        {{-- Linha 1: busca e filtros de cadastro --}}
        <div class="flex flex-wrap items-center gap-2">
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
                    <option value="{{ $categoria->id }}" @selected($filtros['categoria'] === (string) $categoria->id)>{{ $categoria->nome }}</option>
                @endforeach
            </select>

            <select name="marca" data-filtro
                    class="bg-white border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-sm font-medium outline-none focus:ring-2 focus:ring-slate-800">
                <option value="">Todas as marcas</option>
                @foreach ($marcas as $marca)
                    <option value="{{ $marca->id }}" @selected($filtros['marca'] === (string) $marca->id)>{{ $marca->nome }}</option>
                @endforeach
            </select>

            <div class="flex items-center gap-2 ml-auto">
                <select name="status" data-filtro
                        class="bg-white border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-sm font-medium outline-none focus:ring-2 focus:ring-slate-800">
                    <option value="ativos" @selected($filtros['status'] === 'ativos')>Status: Ativos</option>
                    <option value="inativos" @selected($filtros['status'] === 'inativos')>Status: Inativos</option>
                    <option value="todos" @selected($filtros['status'] === 'todos')>Status: Todos</option>
                </select>
            </div>
        </div>

        {{-- Linha 2: situação, período e ordenação --}}
        <div class="flex flex-wrap items-center gap-2">
            <select name="situacao" data-filtro
                    class="bg-white border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-sm font-medium outline-none focus:ring-2 focus:ring-slate-800">
                <option value="todos" @selected($filtros['situacao'] === 'todos')>Situação: Todas abaixo do mínimo</option>
                <option value="abaixo" @selected($filtros['situacao'] === 'abaixo')>Abaixo do mínimo (com estoque)</option>
                <option value="zerado" @selected($filtros['situacao'] === 'zerado')>Zerados</option>
                <option value="negativo" @selected($filtros['situacao'] === 'negativo')>Negativos</option>
            </select>

            <select name="tipo_data" data-filtro
                    class="bg-white border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-sm font-medium outline-none focus:ring-2 focus:ring-slate-800">
                <option value="cadastro" @selected($filtros['tipo_data'] === 'cadastro')>Data: Cadastro</option>
                <option value="alteracao" @selected($filtros['tipo_data'] === 'alteracao')>Data: Última alteração</option>
            </select>

            <label class="flex items-center gap-1.5 text-sm text-gray-500">
                De
                <input type="date" name="data_inicio" value="{{ $filtros['data_inicio'] }}" data-filtro
                       class="bg-white border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
            </label>
            <label class="flex items-center gap-1.5 text-sm text-gray-500">
                Até
                <input type="date" name="data_fim" value="{{ $filtros['data_fim'] }}" data-filtro
                       class="bg-white border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
            </label>

            <a href="{{ route('produtos.estoque-baixo.index') }}"
               class="text-sm text-gray-500 hover:text-gray-800 hover:underline px-1">Limpar filtros</a>

            <div class="flex items-center gap-2 ml-auto">
                <select name="ordenar" data-filtro
                        class="bg-white border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-sm font-medium outline-none focus:ring-2 focus:ring-slate-800">
                    <option value="nome" @selected($filtros['ordenar'] === 'nome')>Ordenar: Alfabética</option>
                    <option value="codigo" @selected($filtros['ordenar'] === 'codigo')>Ordenar: Código</option>
                    <option value="estoque" @selected($filtros['ordenar'] === 'estoque')>Ordenar: Menor estoque</option>
                    <option value="reposicao" @selected($filtros['ordenar'] === 'reposicao')>Ordenar: Maior reposição</option>
                </select>
            </div>
        </div>
    </form>

    <div id="resultado-estoque-baixo">
        @include('produtos.estoque-baixo._tabela', ['itens' => $itens, 'totais' => $totais, 'filtros' => $filtros])
    </div>

    <script>
        let timeoutBuscaEstoqueBaixo;
        const formEstoqueBaixo = document.getElementById('form-estoque-baixo');

        function parametrosEstoqueBaixo() {
            return new URLSearchParams(new FormData(formEstoqueBaixo));
        }

        async function buscarEstoqueBaixo() {
            const url = `{{ route('produtos.estoque-baixo.index') }}?${parametrosEstoqueBaixo().toString()}`;

            const resp = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!resp.ok) return;

            document.getElementById('resultado-estoque-baixo').innerHTML = await resp.text();
            window.history.replaceState(null, '', url);
        }

        const rotasExportarEstoqueBaixo = {
            excel: `{{ route('produtos.estoque-baixo.exportar-excel') }}`,
            pdf: `{{ route('produtos.estoque-baixo.exportar-pdf') }}`,
        };

        // Exporta todos os itens do filtro atual (não só a página que está na tela)
        function exportarEstoqueBaixo(formato) {
            window.location.href = `${rotasExportarEstoqueBaixo[formato]}?${parametrosEstoqueBaixo().toString()}`;
        }

        document.querySelectorAll('[data-filtro]').forEach((el) => {
            el.addEventListener('change', buscarEstoqueBaixo);
        });

        document.getElementById('busca-input').addEventListener('input', () => {
            clearTimeout(timeoutBuscaEstoqueBaixo);
            timeoutBuscaEstoqueBaixo = setTimeout(buscarEstoqueBaixo, 300);
        });

        formEstoqueBaixo.addEventListener('submit', (e) => {
            e.preventDefault();
            buscarEstoqueBaixo();
        });
    </script>
@endsection
