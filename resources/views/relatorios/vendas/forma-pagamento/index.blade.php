@extends('layouts.app')

@section('titulo', 'Relatório de Forma de Pagamento')

{{-- Libera o max-w-5xl do layout para a tabela usar a largura toda da tela --}}
@section('body-class', 'conteudo-largo')

@section('breadcrumb')
    <span>Relatórios</span>
    <span class="text-gray-300">›</span>
    <span>Vendas</span>
    <span class="text-gray-300">›</span>
    <span class="text-gray-700 font-medium">Forma de Pagamento</span>
@endsection

@section('conteudo')
    <div class="flex justify-end items-center gap-2 mb-6">
        <button type="button" onclick="exportarRelatorio('excel')"
                class="bg-green-600 hover:bg-green-500 text-white px-4 py-2.5 rounded-lg font-semibold text-sm transition">
            Exportar Excel
        </button>
        <button type="button" onclick="exportarRelatorio('pdf')"
                class="bg-red-600 hover:bg-red-500 text-white px-4 py-2.5 rounded-lg font-semibold text-sm transition">
            Exportar PDF
        </button>
    </div>

    {{-- Um único form: busca (sempre visível) + campos do modal de filtros --}}
    <form id="form-relatorio" class="flex items-center gap-2 mb-4">
        <select name="visao" id="filtro-visao" data-filtro
                class="bg-white border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-sm font-medium outline-none focus:ring-2 focus:ring-slate-800">
            <option value="resumida" @selected($filtros['visao'] === 'resumida')>Resumida (por modalidade)</option>
            <option value="detalhada" @selected($filtros['visao'] === 'detalhada')>Detalhada (cada pagamento)</option>
        </select>

        {{-- Só fazem sentido na visão detalhada (filtram a lista de pagamentos) --}}
        <div id="filtros-lista" class="{{ $filtros['visao'] === 'detalhada' ? 'flex' : 'hidden' }} items-center gap-2">
            <input type="text" name="busca" id="busca-input" value="{{ $filtros['busca'] }}" placeholder="Nº do documento..." autocomplete="off"
                   class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm w-48 focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">

            <select name="modalidade" data-filtro
                    class="bg-white border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-sm font-medium outline-none focus:ring-2 focus:ring-slate-800">
                <option value="todas" @selected($filtros['modalidade'] === 'todas')>Todas as modalidades</option>
                @foreach ($modalidades as $chave => $rotulo)
                    <option value="{{ $chave }}" @selected($filtros['modalidade'] === $chave)>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>

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
                    <div>
                        <span class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1.5">Período de emissão</span>
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
                        <p class="text-xs text-gray-400 mt-1.5">Por padrão mostra o mês atual. Os cards e o resumo consideram todo o período. Na visão detalhada, a modalidade e o nº do documento filtram a lista.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="filtro-tipo" class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1.5">Documentos</label>
                            <select name="tipo" id="filtro-tipo"
                                    class="w-full bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
                                <option value="todos" @selected($filtros['tipo'] === 'todos')>NFC-e e NF-e</option>
                                <option value="nfce" @selected($filtros['tipo'] === 'nfce')>Só NFC-e</option>
                                <option value="nfe" @selected($filtros['tipo'] === 'nfe')>Só NF-e</option>
                            </select>
                        </div>
                        <div>
                            <label for="filtro-ordenar" class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1.5">Ordenar lista por (detalhada)</label>
                            <select name="ordenar" id="filtro-ordenar"
                                    class="w-full bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
                                <option value="recentes" @selected($filtros['ordenar'] === 'recentes')>Mais recentes</option>
                                <option value="antigos" @selected($filtros['ordenar'] === 'antigos')>Mais antigos</option>
                                <option value="maior_valor" @selected($filtros['ordenar'] === 'maior_valor')>Maior valor</option>
                                <option value="documento" @selected($filtros['ordenar'] === 'documento')>Nº do documento</option>
                            </select>
                        </div>
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

    <div id="resultado-relatorio">
        @include('relatorios.vendas.forma-pagamento._tabela', ['lista' => $lista, 'resumo' => $resumo, 'filtros' => $filtros, 'modalidades' => $modalidades])
    </div>

    <script>
        let timeoutBuscaRelatorio;
        const formRelatorio = document.getElementById('form-relatorio');
        const modalFiltros = document.getElementById('modal-filtros');

        // Valores "sem filtro" dos campos do modal (o período padrão é o mês atual)
        const FILTROS_PADRAO = {
            tipo: 'todos',
            data_inicio: '{{ now()->startOfMonth()->toDateString() }}',
            data_fim: '{{ now()->toDateString() }}',
            ordenar: 'recentes',
        };

        const lerFiltros = () => Object.fromEntries(
            Object.keys(FILTROS_PADRAO).map((nome) => [nome, formRelatorio.elements[nome].value])
        );

        const preencherFiltros = (valores) => {
            Object.entries(valores).forEach(([nome, valor]) => {
                formRelatorio.elements[nome].value = valor;
            });
        };

        // Último estado aplicado: fechar o modal sem clicar em "Aplicar" descarta o que foi mexido
        let filtrosAplicados = lerFiltros();

        function atualizarContadorFiltros() {
            const f = lerFiltros();
            let total = 0;

            if (f.tipo !== FILTROS_PADRAO.tipo) total++;
            if (f.data_inicio !== FILTROS_PADRAO.data_inicio || f.data_fim !== FILTROS_PADRAO.data_fim) total++;
            if (f.ordenar !== FILTROS_PADRAO.ordenar) total++;

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
            buscarRelatorio();
        }

        function limparFiltros() {
            preencherFiltros(FILTROS_PADRAO);
            aplicarFiltros();
        }

        function parametrosRelatorio() {
            return new URLSearchParams(new FormData(formRelatorio));
        }

        async function buscarRelatorio() {
            const url = `{{ route('relatorios.vendas.forma-pagamento.index') }}?${parametrosRelatorio().toString()}`;

            const resp = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!resp.ok) return;

            document.getElementById('resultado-relatorio').innerHTML = await resp.text();
            window.history.replaceState(null, '', url);
        }

        const rotasExportarRelatorio = {
            excel: `{{ route('relatorios.vendas.forma-pagamento.exportar-excel') }}`,
            pdf: `{{ route('relatorios.vendas.forma-pagamento.exportar-pdf') }}`,
        };

        // Exporta tudo do filtro atual (não só a página que está na tela)
        function exportarRelatorio(formato) {
            window.location.href = `${rotasExportarRelatorio[formato]}?${parametrosRelatorio().toString()}`;
        }

        // A visão decide o que aparece (resumo por modalidade OU lista de pagamentos); os campos da lista só existem na detalhada
        const filtrosLista = document.getElementById('filtros-lista');

        document.getElementById('filtro-visao').addEventListener('change', (e) => {
            const detalhada = e.target.value === 'detalhada';
            filtrosLista.classList.toggle('hidden', !detalhada);
            filtrosLista.classList.toggle('flex', detalhada);
        });

        // Fora do modal, visão e modalidade atualizam na hora
        document.querySelectorAll('[data-filtro]').forEach((el) => {
            el.addEventListener('change', buscarRelatorio);
        });

        document.getElementById('busca-input').addEventListener('input', () => {
            clearTimeout(timeoutBuscaRelatorio);
            timeoutBuscaRelatorio = setTimeout(buscarRelatorio, 300);
        });

        // O form tem vários campos (busca + campos do modal), então o Enter não envia sozinho
        document.getElementById('busca-input').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(timeoutBuscaRelatorio);
                buscarRelatorio();
            }
        });

        formRelatorio.addEventListener('submit', (e) => e.preventDefault());

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modalFiltros.classList.contains('hidden')) {
                fecharFiltros();
            }
        });

        atualizarContadorFiltros();
    </script>
@endsection