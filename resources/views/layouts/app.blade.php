<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo', 'PDV Fiscal')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen relative @yield('body-class')">

<!-- Fundo dividido: 1/4 superior cinza e 3/4 branco -->
<div class="absolute inset-0 flex flex-col -z-10 pointer-events-none">
    <div class="w-full h-2/5 bg-gray-200"></div>
    <div class="w-full h-3/5 bg-white"></div>
</div>

<!-- Sidebar: alterna entre expandida (w-50, com texto) e recolhida (w-16, só
     ícones) através da classe 'sidebar-oculta' no <body> — a mesma classe que
     já é definida via @section('body-class', 'sidebar-oculta') nas telas de
     criar/editar nota, e que também pode ser alternada pelo botão do topo. -->
<aside id="nav-principal" class="bg-gray-900 fixed top-0 left-0 h-screen w-50 flex flex-col z-40 overflow-y-auto transition-[width] duration-200 [.sidebar-oculta_&]:w-16 [.sem-sidebar_&]:hidden">
    <div class="px-3 py-4 border-b border-white/10 shrink-0 flex items-center justify-between [.sidebar-oculta_&]:justify-center">
        <div class="px-2 overflow-hidden [.sidebar-oculta_&]:hidden">
            <p class="text-amber-50 font-bold text-lg tracking-tight whitespace-nowrap">PDV Fiscal</p>
            <p class="text-slate-400 text-xs mt-0.5 whitespace-nowrap">Painel administrativo</p>
        </div>
        <button type="button" onclick="toggleSidebar()" title="Minimizar/Expandir menu"
                class="shrink-0 flex items-center justify-center w-9 h-9 rounded-lg text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <rect x="3" y="4" width="18" height="16" rx="2" stroke-width="2"></rect>
                <line x1="9" y1="4" x2="9" y2="20" stroke-width="2"></line>
            </svg>
        </button>
    </div>

    <nav class="flex flex-col gap-1 px-3 py-4 flex-1 overflow-y-auto [.sidebar-oculta_&]:px-2">
        <!-- Menu Dropdown: Cadastros -->
        <div class="flex flex-col">
            <button onclick="toggleCadastros()" 
                    class="flex items-center justify-between w-full px-3 py-2.5 rounded-lg text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 text-sm font-medium transition cursor-pointer [.sidebar-oculta_&]:justify-center [.sidebar-oculta_&]:px-0">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"></path>
                    </svg>
                    <span class="whitespace-nowrap [.sidebar-oculta_&]:hidden">Cadastros</span>
                </span>
                <!-- Seta indicativa -->
                <svg id="seta-cadastros" class="w-4 h-4 transition-transform duration-200 [.sidebar-oculta_&]:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            <!-- Subopções (inicialmente ocultas com 'hidden'; também ocultas com a sidebar recolhida) -->
            <div id="sub-cadastros" class="hidden [.sidebar-oculta_&]:hidden flex flex-col gap-1 pl-4 mt-1 border-l border-white/10 ml-3">
                @if (auth()->user()?->podeVer('produtos'))
                    <a href="{{ route('produtos.index') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                        Produtos
                    </a>
                @endif

                @if (auth()->user()?->podeVer('clientes'))
                    <a href="{{ route('clientes.index') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                        Clientes
                    </a>
                @endif

                @if (auth()->user()?->podeVer('empresa'))
                    <a href="{{ route('empresa.editar') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                        Empresa
                    </a>
                @endif

                @if (auth()->user()?->podeVer('pdvs'))
                    <a href="{{ route('pdvs.index') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                        PDVs
                    </a>
                @endif

                @if (auth()->user()?->podeVer('transportadoras'))
                    {{-- o bloco do flyout "Transportadoras" inteiro --}}
                @endif

                @if (auth()->user()?->isAdmin())
                    <div class="relative">
                        <button id="btn-usuarios" onclick="toggleFlyout('sub-usuarios', 'btn-usuarios')"
                                class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                            <span>Usuários</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>

                        <div id="sub-usuarios" data-flyout data-botao="btn-usuarios"
                            class="hidden fixed bg-gray-900 border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
                            <a href="{{ route('usuarios.index', 'caixa') }}"
                            class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Operador de Caixa
                            </a>

                            <a href="{{ route('usuarios.index', 'supervisor') }}"
                            class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition border-t border-white/10">
                                Supervisor
                            </a>
                            
                            <a href="{{ route('usuarios.index', 'fiscal') }}"
                            class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition border-t border-white/10">
                                Operador Fiscal
                            </a>
                        </div>
                    </div>
                @endif

                <div class="relative">
                    <button id="btn-transportadoras" onclick="toggleFlyout('sub-transportadoras', 'btn-transportadoras')"
                            class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                        <span>Transportadoras</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>

                    <div id="sub-transportadoras" data-flyout data-botao="btn-transportadoras"
                        class="hidden fixed bg-gray-900 border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
                        <a href="{{ route('transportadores.index') }}"
                        class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                            Transportadoras
                        </a>
                        <a href="{{ route('veiculos.index') }}"
                        class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition border-t border-white/10">
                            Veículos
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <!-- Menu Dropdown: Faturamento -->
        <div class="flex flex-col">
            <button onclick="toggleFaturamento()"
                    class="flex items-center justify-between w-full px-3 py-2.5 rounded-lg text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 text-sm font-medium transition cursor-pointer [.sidebar-oculta_&]:justify-center [.sidebar-oculta_&]:px-0">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m-6 4h6m-6 4h4M5 3h14a1 1 0 011 1v16l-3-2-3 2-3-2-3 2-3-2-3 2V4a1 1 0 011-1z"></path>
                    </svg>
                    <span class="whitespace-nowrap [.sidebar-oculta_&]:hidden">Faturamento</span>
                </span>
                <svg id="seta-faturamento" class="w-4 h-4 transition-transform duration-200 [.sidebar-oculta_&]:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            <div id="sub-faturamento" class="hidden [.sidebar-oculta_&]:hidden flex flex-col gap-1 pl-4 mt-1 border-l border-white/10 ml-3">
                <!-- Nota Fiscal (nível 2, abre lateral) -->
                <div class="relative">
                    <button id="btn-notafiscal" onclick="toggleNotaFiscal()"
                            class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                        <span>Nota Fiscal</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>

                    <!-- Flyout lateral: 'fixed' + posição calculada via JS, para escapar do overflow-y-auto do menu -->
                    <div id="sub-notafiscal" class="hidden fixed bg-gray-900 border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
                        <a href="{{ route('notasfiscais.index') }}"
                        class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                            Saída
                        </a>
                        
                        <a href="{{ route('series-nfe.index') }}"
                        class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition border-t border-white/10">
                            Última numeração de NF-e
                        </a>   
                    </div>

                </div>
            </div>
        </div>
    </nav>

    <div class="px-3 py-4 border-t border-white/10 shrink-0 [.sidebar-oculta_&]:px-2">
        <button onclick="sincronizarAgora()" id="btn-sincronizar"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 text-sm font-medium transition cursor-pointer [.sidebar-oculta_&]:justify-center [.sidebar-oculta_&]:px-0">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            <span class="whitespace-nowrap [.sidebar-oculta_&]:hidden">Atualizar Caixa</span>
        </button>
    </div>
</aside>

    <!-- Conteúdo principal -->
    <div id="conteudo-principal" class="ml-56 min-h-screen transition-[margin] duration-200 [.sidebar-oculta_&]:ml-16 [.sem-sidebar_&]:ml-0">
        <main class="max-w-5xl mx-auto p-8 [.conteudo-largo_&]:max-w-none [.conteudo-largo_&]:mx-0 [.conteudo-largo_&]:px-6 [.conteudo-largo_&]:pt-4">

            @php
                // Mapa central do breadcrumb: prefixo da rota => [Grupo, Página]
                $breadcrumbMapa = [
                    'produtos' => ['Cadastros', 'Produtos'],
                    'clientes' => ['Cadastros', 'Clientes'],
                    'empresa'  => ['Cadastros', 'Empresa'],
                    'pdvs'     => ['Cadastros', 'PDVs'],
                    'notasfiscais' => ['Faturamento', 'Nota Fiscal - Saída'],
                    'series-nfe' => ['Faturamento', 'Séries de NF-e'],
                    'transportadores' => ['Cadastros', 'Transportadoras'],
                    'veiculos' => ['Cadastros', 'Veículos'],
                ];

                $rotaAtual = \Illuminate\Support\Facades\Route::currentRouteName();$prefixoRota = $rotaAtual ? explode('.', $rotaAtual)[0] : null;
                $breadcrumbAuto = $breadcrumbMapa[$prefixoRota] ?? null;
            @endphp

            @hasSection('breadcrumb')
                <div class="flex items-center gap-2 mb-5 text-sm text-gray-500">
                    @yield('breadcrumb')
                </div>
            @elseif ($breadcrumbAuto)
                <div class="flex items-center gap-2 mb-5 text-sm text-gray-500">
                    <span>{{ $breadcrumbAuto[0] }}</span>
                    <span class="text-gray-300">›</span>
                    <span class="text-gray-700 font-medium">{{ $breadcrumbAuto[1] }}</span>
                </div>
            @endif

            @if (session('sucesso'))
                <div class="bg-green-100 text-green-800 border border-green-300 rounded px-4 py-3 mb-6">
                    {{ session('sucesso') }}
                </div>
            @endif

            @yield('conteudo')
        </main>
    </div>

    @yield('scripts')

<script>

function fecharMenusAcoes() {
    document.querySelectorAll('[data-menu-acoes-painel]').forEach(p => p.classList.add('hidden'));
}

document.addEventListener('click', (e) => {
    const botao = e.target.closest('[data-menu-acoes]');

    if (!botao) {
        // clique fora do menu fecha; cliques nos itens do menu seguem normalmente
        if (!e.target.closest('[data-menu-acoes-painel]')) fecharMenusAcoes();
        return;
    }

    const painel = botao.parentElement.querySelector('[data-menu-acoes-painel]');
    const estavaFechado = painel.classList.contains('hidden');

    fecharMenusAcoes();
    if (!estavaFechado) return; // segundo clique no mesmo botão só fecha

    painel.classList.remove('hidden'); // precisa estar visível para medir o tamanho

    const rect = botao.getBoundingClientRect();
    const altura = painel.offsetHeight;
    const largura = painel.offsetWidth;
    const abrirParaCima = rect.bottom + altura + 8 > window.innerHeight;

    painel.style.top = (abrirParaCima ? rect.top - altura - 4 : rect.bottom + 4) + 'px';
    painel.style.left = Math.max(8, rect.right - largura) + 'px'; // alinha a borda direita com o botão
});

// o menu é fixo na tela, então fecha ao rolar ou redimensionar para não ficar solto do botão
window.addEventListener('scroll', fecharMenusAcoes, true);
window.addEventListener('resize', fecharMenusAcoes);
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') fecharMenusAcoes();
});

function toggleFlyout(idFlyout, idBotao) {
    const flyout = document.getElementById(idFlyout);
    const botao = document.getElementById(idBotao);
    const estaAbrindo = flyout.classList.contains('hidden');

    // fecha qualquer outro aberto antes
    document.querySelectorAll('[data-flyout]').forEach(el => el.classList.add('hidden'));

    if (estaAbrindo) {
        const rect = botao.getBoundingClientRect();
        flyout.style.top = rect.top + 'px';
        flyout.style.left = (rect.right + 4) + 'px';
        flyout.classList.remove('hidden');
    }
}

document.addEventListener('click', function (evento) {
    document.querySelectorAll('[data-flyout]').forEach(flyout => {
        const botao = document.getElementById(flyout.dataset.botao);
        if (!flyout.classList.contains('hidden')
            && !flyout.contains(evento.target)
            && !botao.contains(evento.target)) {
            flyout.classList.add('hidden');
        }
    });
});

function toggleSidebar() {
        document.body.classList.toggle('sidebar-oculta');
    }

function toggleCadastros() {
    if (document.body.classList.contains('sidebar-oculta')) {
            document.body.classList.remove('sidebar-oculta');
        }

        const subMenu = document.getElementById('sub-cadastros');
        const seta = document.getElementById('seta-cadastros');
    
        subMenu.classList.toggle('hidden');
        seta.classList.toggle('rotate-180');
    }   

    function toggleFaturamento() {
        if (document.body.classList.contains('sidebar-oculta')) {
            document.body.classList.remove('sidebar-oculta');
        }

        document.getElementById('sub-faturamento').classList.toggle('hidden');
        document.getElementById('seta-faturamento').classList.toggle('rotate-180');
    }

    function toggleNotaFiscal() {
        const flyout = document.getElementById('sub-notafiscal');
        const botao = document.getElementById('btn-notafiscal');
        const estaAbrindo = flyout.classList.contains('hidden');

        if (estaAbrindo) {
            const rect = botao.getBoundingClientRect();
            flyout.style.top = rect.top + 'px';
            flyout.style.left = (rect.right + 4) + 'px';
        }

        flyout.classList.toggle('hidden');
    }

    document.addEventListener('click', function (evento) {
        const flyout = document.getElementById('sub-notafiscal');
        const botao = document.getElementById('btn-notafiscal');

        if (!flyout.classList.contains('hidden')
            && !flyout.contains(evento.target)
            && !botao.contains(evento.target)) {
            flyout.classList.add('hidden');
        }
    });

    async function sincronizarAgora() {
        const btn = document.getElementById('btn-sincronizar');
        const textoOriginal = btn.innerText;

        btn.disabled = true;
        btn.innerText = 'Sincronizando...';

        try {
            const resp = await fetch('{{ route("sincronizacao.executar") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
            });

            const resultado = await resp.json();

            if (resultado.sucesso) {
                alert(
                    `Sincronização concluída!\n` +
                    `Produtos atualizados: ${resultado.produtos_atualizados}\n` +
                    `Vendas enviadas: ${resultado.vendas_enviadas}` +
                    (resultado.vendas_falhas > 0 ? `\nVendas com falha: ${resultado.vendas_falhas}` : '') +
                    (resultado.usuarios_ok
                        ? `\nUsuários atualizados: ${resultado.usuarios_atualizados}`
                        : `\nFalha ao sincronizar usuários: ${resultado.usuarios_erro}`)
                );
            } else {
                alert('Erro ao sincronizar: ' + resultado.erro);
            }
        } catch (e) {
            alert('Erro de conexão ao tentar sincronizar.');
        }

        btn.disabled = false;
        btn.innerText = textoOriginal;
    }
    </script>
</body>
</html>