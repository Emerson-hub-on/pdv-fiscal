@extends('layouts.app')

@section('titulo', 'Relatório de Notas de Entrada')

{{-- Libera o max-w-5xl do layout para a tabela usar a largura toda da tela --}}
@section('body-class', 'conteudo-largo')

@section('breadcrumb')
    <span>Faturamento</span>
    <span class="text-gray-300">›</span>
    <span>Relatório</span>
    <span class="text-gray-300">›</span>
    <span class="text-gray-700 font-medium">Entrada</span>
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
        <select name="tipo_busca" data-filtro
                class="bg-white border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-sm font-medium outline-none focus:ring-2 focus:ring-slate-800">
            <option value="numero" @selected($filtros['tipo_busca'] === 'numero')>Número da nota</option>
            <option value="fornecedor" @selected($filtros['tipo_busca'] === 'fornecedor')>Fornecedor</option>
            <option value="documento" @selected($filtros['tipo_busca'] === 'documento')>CPF/CNPJ</option>
            <option value="chave" @selected($filtros['tipo_busca'] === 'chave')>Chave de acesso</option>
            <option value="produto" @selected($filtros['tipo_busca'] === 'produto')>Produto</option>
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
                            <label for="filtro-status" class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1.5">Notas</label>
                            <select name="status" id="filtro-status"
                                    class="w-full bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
                                <option value="finalizada" @selected($filtros['status'] === 'finalizada')>Finalizadas</option>
                                <option value="rascunho" @selected($filtros['status'] === 'rascunho')>Rascunhos</option>
                                <option value="todas" @selected($filtros['status'] === 'todas')>Todas</option>
                            </select>
                        </div>

                        <div>
                            <label for="filtro-origem" class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1.5">Origem</label>
                            <select name="origem" id="filtro-origem"
                                    class="w-full bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
                                <option value="todas" @selected($filtros['origem'] === 'todas')>Todas</option>
                                <option value="xml" @selected($filtros['origem'] === 'xml')>Importadas do XML</option>
                                <option value="manual" @selected($filtros['origem'] === 'manual')>Digitadas</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="filtro-operacao" class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1.5">Operação</label>
                            <select name="operacao" id="filtro-operacao"
                                    class="w-full bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
                                <option value="">Todas as operações</option>
                                @foreach ($operacoes as $operacao)
                                    <option value="{{ $operacao->id }}" @selected($filtros['operacao'] === (string) $operacao->id)>{{ $operacao->descricao }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="filtro-operador" class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1.5">Usuário</label>
                            <select name="operador" id="filtro-operador"
                                    class="w-full bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
                                <option value="">Todos os usuários</option>
                                @foreach ($operadores as $operador)
                                    <option value="{{ $operador->id }}" @selected($filtros['operador'] === (string) $operador->id)>{{ $operador->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="filtro-tipo-data" class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1.5">Período</label>
                        <select name="tipo_data" id="filtro-tipo-data"
                                class="w-full bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800 mb-2">
                            <option value="entrada" @selected($filtros['tipo_data'] === 'entrada')>Data de entrada</option>
                            <option value="emissao" @selected($filtros['tipo_data'] === 'emissao')>Data de emissão</option>
                            <option value="finalizacao" @selected($filtros['tipo_data'] === 'finalizacao')>Data de finalização</option>
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
                        <p class="text-xs text-gray-400 mt-1.5">Por padrão mostra o mês atual. Apague as datas para ver todo o histórico.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="filtro-visao" class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1.5">Exportação (PDF/Excel)</label>
                            <select name="visao" id="filtro-visao"
                                    class="w-full bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
                                <option value="detalhada" @selected($filtros['visao'] === 'detalhada')>Com itens (detalhada)</option>
                                <option value="resumida" @selected($filtros['visao'] === 'resumida')>Só as notas (resumida)</option>
                            </select>
                        </div>

                        <div>
                            <label for="filtro-ordenar" class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1.5">Ordenar por</label>
                            <select name="ordenar" id="filtro-ordenar"
                                    class="w-full bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
                                <option value="recentes" @selected($filtros['ordenar'] === 'recentes')>Mais recentes</option>
                                <option value="antigos" @selected($filtros['ordenar'] === 'antigos')>Mais antigas</option>
                                <option value="numero" @selected($filtros['ordenar'] === 'numero')>Número da nota</option>
                                <option value="valor" @selected($filtros['ordenar'] === 'valor')>Maior valor</option>
                                <option value="fornecedor" @selected($filtros['ordenar'] === 'fornecedor')>Fornecedor (A-Z)</option>
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
        @include('faturamento.relatorios.entrada._tabela', ['entradas' => $entradas, 'totais' => $totais, 'filtros' => $filtros])
    </div>

    {{-- Modal dos itens da nota (fica fora de #resultado-relatorio para não sumir quando a tabela recarrega) --}}
    <div id="modal-itens" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60" onclick="fecharItens()"></div>

        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-full flex flex-col">
            <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 bg-gray-700 rounded-t-xl">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-wide text-amber-50/70">Itens da nota</p>
                    <h2 id="modal-itens-titulo" class="text-base font-semibold text-amber-50"></h2>
                    <p id="modal-itens-subtitulo" class="text-sm text-amber-50/80 truncate"></p>
                </div>
                <button type="button" onclick="fecharItens()" class="text-amber-50/70 hover:text-amber-50 transition shrink-0" aria-label="Fechar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div id="modal-itens-corpo" class="px-6 py-5 overflow-y-auto"></div>

            <div class="flex justify-end px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
                <button type="button" onclick="fecharItens()"
                        class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-5 py-2.5 rounded-lg font-semibold text-sm transition">
                    Fechar
                </button>
            </div>
        </div>
    </div>

    <script>
        let timeoutBuscaRelatorio;
        const formRelatorio = document.getElementById('form-relatorio');
        const modalFiltros = document.getElementById('modal-filtros');

        // Valores "sem filtro" dos campos do modal (o período padrão é o mês atual)
        const FILTROS_PADRAO = {
            status: 'finalizada',
            origem: 'todas',
            operacao: '',
            operador: '',
            tipo_data: 'entrada',
            data_inicio: '{{ now()->startOfMonth()->toDateString() }}',
            data_fim: '{{ now()->toDateString() }}',
            visao: 'detalhada',
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

            if (f.status !== FILTROS_PADRAO.status) total++;
            if (f.origem !== FILTROS_PADRAO.origem) total++;
            if (f.operacao) total++;
            if (f.operador) total++;
            if (f.tipo_data !== FILTROS_PADRAO.tipo_data) total++;
            if (f.data_inicio !== FILTROS_PADRAO.data_inicio || f.data_fim !== FILTROS_PADRAO.data_fim) total++;
            if (f.visao !== FILTROS_PADRAO.visao) total++;

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
            const url = `{{ route('faturamento.relatorios.entrada.index') }}?${parametrosRelatorio().toString()}`;

            const resp = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!resp.ok) return;

            document.getElementById('resultado-relatorio').innerHTML = await resp.text();
            window.history.replaceState(null, '', url);
        }

        const rotasExportarRelatorio = {
            excel: `{{ route('faturamento.relatorios.entrada.exportar-excel') }}`,
            pdf: `{{ route('faturamento.relatorios.entrada.exportar-pdf') }}`,
        };

        // Exporta todas as notas do filtro atual (não só a página que está na tela)
        function exportarRelatorio(formato) {
            window.location.href = `${rotasExportarRelatorio[formato]}?${parametrosRelatorio().toString()}`;
        }

        // Fora do modal, só o tipo de busca atualiza na hora
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

        // Modal de itens: o conteúdo de cada nota já está na página (<template>), é só copiar para o modal
        const modalItens = document.getElementById('modal-itens');

        function abrirItens(botao) {
            const modelo = document.getElementById('itens-nota-' + botao.dataset.nota);
            if (!modelo) return;

            document.getElementById('modal-itens-titulo').innerText = botao.dataset.titulo;
            document.getElementById('modal-itens-subtitulo').innerText = botao.dataset.subtitulo;
            document.getElementById('modal-itens-corpo').innerHTML = modelo.innerHTML;
            modalItens.classList.remove('hidden');
        }

        function fecharItens() {
            modalItens.classList.add('hidden');
        }

        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;

            if (!modalItens.classList.contains('hidden')) {
                fecharItens();
            } else if (!modalFiltros.classList.contains('hidden')) {
                fecharFiltros();
            }
        });

        atualizarContadorFiltros();
    </script>
@endsection
