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

    {{-- Um único form: busca (sempre visível) + campos do modal de filtros --}}
    <form id="form-estoque-baixo" class="flex items-center gap-2 mb-4">
        <select name="tipo_busca" data-filtro
                class="bg-white border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-sm font-medium outline-none focus:ring-2 focus:ring-slate-800">
            <option value="nome" @selected($filtros['tipo_busca'] === 'nome')>Descrição</option>
            <option value="codigo_interno" @selected($filtros['tipo_busca'] === 'codigo_interno')>Código interno</option>
            <option value="codigo_barras" @selected($filtros['tipo_busca'] === 'codigo_barras')>Código de barras</option>
            <option value="referencia" @selected($filtros['tipo_busca'] === 'referencia')>Referência</option>
        </select>

        <input type="text" name="busca" id="busca-input" value="{{ $filtros['busca'] }}" placeholder="Buscar..." autocomplete="off"
               class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm w-64 focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">

        <button type="button" onclick="abrirFiltros()"
                class="flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 px-4 py-2 rounded-lg text-sm font-medium transition">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
            </svg>
            Filtros
            <span id="contador-filtros" class="hidden bg-blue-600 text-white text-xs font-semibold rounded-full px-1.5 py-0.5 leading-none">0</span>
        </button>

        {{-- Modal de filtros --}}
        <div id="modal-filtros" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" onclick="fecharFiltros()"></div>

            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg max-h-full overflow-y-auto">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-800">Filtros</h2>
                    <button type="button" onclick="fecharFiltros()" class="text-gray-400 hover:text-gray-600 transition" aria-label="Fechar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-5 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="filtro-categoria" class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1.5">Categoria</label>
                            <select name="categoria" id="filtro-categoria"
                                    class="w-full bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
                                <option value="">Todas as categorias</option>
                                @foreach ($categorias as $categoria)
                                    <option value="{{ $categoria->id }}" @selected($filtros['categoria'] === (string) $categoria->id)>{{ $categoria->nome }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="filtro-marca" class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1.5">Marca</label>
                            <select name="marca" id="filtro-marca"
                                    class="w-full bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
                                <option value="">Todas as marcas</option>
                                @foreach ($marcas as $marca)
                                    <option value="{{ $marca->id }}" @selected($filtros['marca'] === (string) $marca->id)>{{ $marca->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="filtro-situacao" class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1.5">Situação do estoque</label>
                            <select name="situacao" id="filtro-situacao"
                                    class="w-full bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
                                <option value="todos" @selected($filtros['situacao'] === 'todos')>Todas abaixo do mínimo</option>
                                <option value="abaixo" @selected($filtros['situacao'] === 'abaixo')>Abaixo do mínimo (com estoque)</option>
                                <option value="zerado" @selected($filtros['situacao'] === 'zerado')>Zerados</option>
                                <option value="negativo" @selected($filtros['situacao'] === 'negativo')>Negativos</option>
                            </select>
                        </div>

                        <div>
                            <label for="filtro-status" class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1.5">Status do produto</label>
                            <select name="status" id="filtro-status"
                                    class="w-full bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
                                <option value="ativos" @selected($filtros['status'] === 'ativos')>Ativos</option>
                                <option value="inativos" @selected($filtros['status'] === 'inativos')>Inativos</option>
                                <option value="todos" @selected($filtros['status'] === 'todos')>Todos</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="filtro-tipo-data" class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1.5">Período</label>
                        <select name="tipo_data" id="filtro-tipo-data"
                                class="w-full bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800 mb-2">
                            <option value="cadastro" @selected($filtros['tipo_data'] === 'cadastro')>Data de cadastro</option>
                            <option value="alteracao" @selected($filtros['tipo_data'] === 'alteracao')>Última alteração</option>
                        </select>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="filtro-data-inicio" class="block text-xs text-gray-500 mb-1">De</label>
                                <input type="date" name="data_inicio" id="filtro-data-inicio" value="{{ $filtros['data_inicio'] }}"
                                       class="w-full bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
                            </div>
                            <div>
                                <label for="filtro-data-fim" class="block text-xs text-gray-500 mb-1">Até</label>
                                <input type="date" name="data_fim" id="filtro-data-fim" value="{{ $filtros['data_fim'] }}"
                                       class="w-full bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="filtro-ordenar" class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1.5">Ordenar por</label>
                        <select name="ordenar" id="filtro-ordenar"
                                class="w-full bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
                            <option value="nome" @selected($filtros['ordenar'] === 'nome')>Ordem alfabética</option>
                            <option value="codigo" @selected($filtros['ordenar'] === 'codigo')>Código</option>
                            <option value="estoque" @selected($filtros['ordenar'] === 'estoque')>Menor estoque</option>
                            <option value="reposicao" @selected($filtros['ordenar'] === 'reposicao')>Maior reposição</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-between px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
                    <button type="button" onclick="limparFiltros()"
                            class="text-sm font-medium text-gray-500 hover:text-gray-700 transition">
                        Limpar filtros
                    </button>
                    <button type="button" onclick="aplicarFiltros()"
                            class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2.5 rounded-lg font-semibold text-sm transition">
                        Aplicar
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div id="resultado-estoque-baixo">
        @include('produtos.estoque-baixo._tabela', ['itens' => $itens, 'totais' => $totais, 'filtros' => $filtros])
    </div>

    <script>
        let timeoutBuscaEstoqueBaixo;
        const formEstoqueBaixo = document.getElementById('form-estoque-baixo');
        const modalFiltros = document.getElementById('modal-filtros');

        // Valores "sem filtro" dos campos do modal
        const FILTROS_PADRAO = {
            categoria: '',
            marca: '',
            situacao: 'todos',
            status: 'ativos',
            tipo_data: 'cadastro',
            data_inicio: '',
            data_fim: '',
            ordenar: 'nome',
        };

        const lerFiltros = () => Object.fromEntries(
            Object.keys(FILTROS_PADRAO).map((nome) => [nome, formEstoqueBaixo.elements[nome].value])
        );

        const preencherFiltros = (valores) => {
            Object.entries(valores).forEach(([nome, valor]) => {
                formEstoqueBaixo.elements[nome].value = valor;
            });
        };

        // Último estado aplicado: fechar o modal sem clicar em "Aplicar" descarta o que foi mexido
        let filtrosAplicados = lerFiltros();

        function atualizarContadorFiltros() {
            const f = lerFiltros();
            let total = 0;

            if (f.categoria) total++;
            if (f.marca) total++;
            if (f.situacao !== FILTROS_PADRAO.situacao) total++;
            if (f.status !== FILTROS_PADRAO.status) total++;
            if (f.data_inicio || f.data_fim) total++;

            const badge = document.getElementById('contador-filtros');
            badge.innerText = total;
            badge.classList.toggle('hidden', total === 0);
        }

        function abrirFiltros() {
            modalFiltros.classList.remove('hidden');
        }

        function fecharFiltros() {
            preencherFiltros(filtrosAplicados);
            modalFiltros.classList.add('hidden');
        }

        function aplicarFiltros() {
            filtrosAplicados = lerFiltros();
            modalFiltros.classList.add('hidden');
            atualizarContadorFiltros();
            buscarEstoqueBaixo();
        }

        function limparFiltros() {
            preencherFiltros(FILTROS_PADRAO);
            aplicarFiltros();
        }

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

        // Fora do modal, só o tipo de busca atualiza na hora
        document.querySelectorAll('[data-filtro]').forEach((el) => {
            el.addEventListener('change', buscarEstoqueBaixo);
        });

        document.getElementById('busca-input').addEventListener('input', () => {
            clearTimeout(timeoutBuscaEstoqueBaixo);
            timeoutBuscaEstoqueBaixo = setTimeout(buscarEstoqueBaixo, 300);
        });

        // O form tem vários campos (busca + datas do modal), então o Enter não envia sozinho
        document.getElementById('busca-input').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(timeoutBuscaEstoqueBaixo);
                buscarEstoqueBaixo();
            }
        });

        formEstoqueBaixo.addEventListener('submit', (e) => e.preventDefault());

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modalFiltros.classList.contains('hidden')) {
                fecharFiltros();
            }
        });

        atualizarContadorFiltros();
    </script>
@endsection