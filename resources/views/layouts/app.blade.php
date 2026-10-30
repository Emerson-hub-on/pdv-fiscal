<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- Aba do navegador: "Produtos - Cyberly Sistema"; sem título na tela, só o nome do sistema --}}
    @hasSection('titulo')
        <title>@yield('titulo') - Cyberly Sistema</title>
    @else
        <title>Cyberly Sistema</title>
    @endif
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
            <p class="text-amber-50 font-bold text-lg tracking-tight whitespace-nowrap">Cyberly Sistema</p>
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
                    <div class="relative">
                        <button id="btn-produtos" onclick="toggleFlyout('sub-produtos', 'btn-produtos')"
                                class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                            <span>Produtos</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>

                        <div id="sub-produtos" data-flyout data-botao="btn-produtos"
                            class="hidden fixed bg-gray-900 border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
                            <a href="{{ route('produtos.index') }}"
                            class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Cadastros
                            </a>
                            <a href="{{ route('produtos.inventario.index') }}"
                            class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition border-t border-white/10">
                                Inventário
                            </a>
                            <a href="{{ route('produtos.estoque-baixo.index') }}"
                            class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition border-t border-white/10">
                                Estoque Baixo
                            </a>
                            <a href="{{ route('produtos.entradas.index') }}"
                            class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition border-t border-white/10">
                                Entrada de Produtos
                            </a>
                            <a href="{{ route('produtos.saidas.index') }}"
                            class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition border-t border-white/10">
                                Movimentação de Produtos
                            </a>
                        </div>
                    </div>
                @endif

                @if (auth()->user()?->podeVer('clientes'))
                    <a href="{{ route('clientes.index') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                        Clientes
                    </a>
                @endif

                @if (auth()->user()?->podeVer('fornecedores'))
                    <a href="{{ route('fornecedores.index') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                        Fornecedores
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

                            <a href="{{ route('usuarios.index', 'tecnico') }}"
                            class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition border-t border-white/10">
                                Técnico
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
                        <a href="{{ route('condutores.index') }}"
                        class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition border-t border-white/10">
                            Condutores
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
                    <button id="btn-notafiscal" onclick="toggleFlyout('sub-notafiscal', 'btn-notafiscal')"
                            class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                        <span>Nota Fiscal</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>

                    <!-- Flyout lateral: 'fixed' + posição calculada via JS, para escapar do overflow-y-auto do menu -->
                    <div id="sub-notafiscal" data-flyout data-botao="btn-notafiscal" class="hidden fixed bg-gray-900 border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
                        <a href="{{ route('notasfiscais.index') }}"
                        class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                            Saída
                        </a>

                        <a href="{{ route('entradas-nota.index') }}"
                        class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition border-t border-white/10">
                            Entrada
                        </a>
                        
                        <a href="{{ route('series-nfe.index') }}"
                        class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition border-t border-white/10">
                            Última numeração de NF-e
                        </a>   
                    </div>

                </div>

                <!-- MDF-e (nível 2, abre lateral) -->
                <div class="relative">
                    <button id="btn-mdfe" onclick="toggleFlyout('sub-mdfe', 'btn-mdfe')"
                            class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                        <span>MDF-e</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>

                    <div id="sub-mdfe" data-flyout data-botao="btn-mdfe"
                        class="hidden fixed bg-gray-900 border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
                        <a href="{{ route('mdfes.index') }}"
                        class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                            Manifestos
                        </a>
                        <a href="{{ route('mdfes.numeracao.editar') }}"
                        class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition border-t border-white/10">
                            Última numeração
                        </a>
                    </div>
                </div>

                <!-- Relatório (nível 2, abre lateral) -->
                <div class="relative">
                    <button id="btn-relatorio-faturamento" onclick="toggleFlyout('sub-relatorio-faturamento', 'btn-relatorio-faturamento')"
                            class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                        <span>Relatório</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>

                    <div id="sub-relatorio-faturamento" data-flyout data-botao="btn-relatorio-faturamento"
                         class="hidden fixed bg-gray-900 border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
                        <a href="{{ route('faturamento.relatorios.saida.index') }}"
                        class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                            Saída
                        </a>
                        <a href="{{ route('faturamento.relatorios.entrada.index') }}"
                        class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                            Entrada
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Dropdown: Financeiro -->
        <div class="flex flex-col">
            <button onclick="toggleFinanceiro()"
                    class="flex items-center justify-between w-full px-3 py-2.5 rounded-lg text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 text-sm font-medium transition cursor-pointer [.sidebar-oculta_&]:justify-center [.sidebar-oculta_&]:px-0">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="whitespace-nowrap [.sidebar-oculta_&]:hidden">Financeiro</span>
                </span>
                <svg id="seta-financeiro" class="w-4 h-4 transition-transform duration-200 [.sidebar-oculta_&]:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            <div id="sub-financeiro" class="hidden [.sidebar-oculta_&]:hidden flex flex-col gap-1 pl-4 mt-1 border-l border-white/10 ml-3">
                @if (auth()->user()?->podeVer('contas-pagar'))
                    <a href="{{ route('contas-pagar.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                        Contas a pagar
                    </a>
                    <a href="{{ route('contas-receber.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                    Contas a receber
                    </a>
                    <a href="{{ route('pedidos-compra.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                        Pedidos de compra
                    </a>

                    <!-- Relatório (nível 2, abre lateral) -->
                    <div class="relative">
                        <button id="btn-relatorio-financeiro" onclick="toggleFlyout('sub-relatorio-financeiro', 'btn-relatorio-financeiro')"
                                class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                            <span>Relatório</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>

                        <div id="sub-relatorio-financeiro" data-flyout data-botao="btn-relatorio-financeiro"
                             class="hidden fixed bg-gray-900 border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
                            <a href="{{ route('financeiro.relatorios.contas-pagar.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Contas a pagar
                            </a>
                            <a href="{{ route('financeiro.relatorios.contas-receber.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Contas a receber
                            </a>
                            <a href="{{ route('financeiro.relatorios.pedidos-compra.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Pedidos de compra
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Menu Dropdown: Pedidos -->
        <div class="flex flex-col">
            <button onclick="togglePedidos()"
                    class="flex items-center justify-between w-full px-3 py-2.5 rounded-lg text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 text-sm font-medium transition cursor-pointer [.sidebar-oculta_&]:justify-center [.sidebar-oculta_&]:px-0">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    <span class="whitespace-nowrap [.sidebar-oculta_&]:hidden">Pedidos</span>
                </span>
                <svg id="seta-pedidos" class="w-4 h-4 transition-transform duration-200 [.sidebar-oculta_&]:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            <div id="sub-pedidos" class="hidden [.sidebar-oculta_&]:hidden flex flex-col gap-1 pl-4 mt-1 border-l border-white/10 ml-3">
                @if (auth()->user()?->podeVer('notas'))
                    <a href="{{ route('pedidos-venda.index') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                        Pedido de Venda
                    </a>
                    <a href="{{ route('ordens-servico.index') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                        Ordem de Serviço
                    </a>

                    <!-- Relatório (nível 2, abre lateral) -->
                    <div class="relative">
                        <button id="btn-relatorio-pedidos" onclick="toggleFlyout('sub-relatorio-pedidos', 'btn-relatorio-pedidos')"
                                class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                            <span>Relatório</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>

                        <div id="sub-relatorio-pedidos" data-flyout data-botao="btn-relatorio-pedidos"
                             class="hidden fixed bg-gray-900 border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
                            <a href="{{ route('pedidos.relatorios.venda.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Pedido de venda
                            </a>
                            <a href="{{ route('pedidos.relatorios.ordem-servico.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Ordem de serviço
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Menu Dropdown: Relatórios -->
        <div class="flex flex-col">
            <button onclick="toggleRelatorios()"
                    class="flex items-center justify-between w-full px-3 py-2.5 rounded-lg text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 text-sm font-medium transition cursor-pointer [.sidebar-oculta_&]:justify-center [.sidebar-oculta_&]:px-0">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <span class="whitespace-nowrap [.sidebar-oculta_&]:hidden">Relatórios</span>
                </span>
                <svg id="seta-relatorios" class="w-4 h-4 transition-transform duration-200 [.sidebar-oculta_&]:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            <div id="sub-relatorios" class="hidden [.sidebar-oculta_&]:hidden flex flex-col gap-1 pl-4 mt-1 border-l border-white/10 ml-3">
                @if (auth()->user()?->podeVer('notas'))
                    <!-- Vendas (nível 2, abre lateral) -->
                    <div class="relative">
                        <button id="btn-relatorio-vendas" onclick="toggleFlyout('sub-relatorio-vendas', 'btn-relatorio-vendas')"
                                class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                            <span>Vendas</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>

                        <div id="sub-relatorio-vendas" data-flyout data-botao="btn-relatorio-vendas"
                             class="hidden fixed bg-gray-900 border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
                            <a href="{{ route('relatorios.vendas.detalhada.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Detalhada
                            </a>
                            <a href="{{ route('relatorios.vendas.margem-lucro.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Margem de Lucro
                            </a>
                            <a href="{{ route('relatorios.vendas.curva-abc.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Curva ABC
                            </a>
                            <a href="{{ route('relatorios.vendas.forma-pagamento.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Forma de Pagamento
                            </a>
                            <a href="{{ route('relatorios.vendas.tributacao.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Regime/Alíquota e Tributação
                            </a>
                            <a href="{{ route('relatorios.vendas.vendedor.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Vendas por Vendedor
                            </a>
                        </div>
                    </div>

                    <!-- Gráficos (nível 2, abre lateral) -->
                    <div class="relative">
                        <button id="btn-relatorio-graficos" onclick="toggleFlyout('sub-relatorio-graficos', 'btn-relatorio-graficos')"
                                class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                            <span>Gráficos</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>

                        <div id="sub-relatorio-graficos" data-flyout data-botao="btn-relatorio-graficos"
                             class="hidden fixed bg-gray-900 border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
                            <a href="{{ route('relatorios.graficos.comparativo-mensal.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Comparativo mensal de vendas
                            </a>
                            <a href="{{ route('relatorios.graficos.comparativo-semanal.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Comparativo semanal de vendas
                            </a>
                            <a href="{{ route('relatorios.graficos.comparativo-diario.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Comparativo diário de vendas
                            </a>
                        </div>
                    </div>

                    <!-- Caixa (nível 2, abre lateral) -->
                    <div class="relative">
                        <button id="btn-relatorio-caixa" onclick="toggleFlyout('sub-relatorio-caixa', 'btn-relatorio-caixa')"
                                class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                            <span>Caixa</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>

                        <div id="sub-relatorio-caixa" data-flyout data-botao="btn-relatorio-caixa"
                             class="hidden fixed bg-gray-900 border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
                            <a href="{{ route('relatorios.caixa.abertura') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Abertura
                            </a>
                            <a href="{{ route('relatorios.caixa.fechamento') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Fechamento
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Menu Dropdown: Fiscal -->
        @if (auth()->user()?->podeVer('notas'))
            <div class="flex flex-col">
                <button onclick="toggleFiscal()"
                        class="flex items-center justify-between w-full px-3 py-2.5 rounded-lg text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 text-sm font-medium transition cursor-pointer [.sidebar-oculta_&]:justify-center [.sidebar-oculta_&]:px-0">
                    <span class="flex items-center gap-3">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="whitespace-nowrap [.sidebar-oculta_&]:hidden">Fiscal</span>
                    </span>
                    <svg id="seta-fiscal" class="w-4 h-4 transition-transform duration-200 [.sidebar-oculta_&]:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <div id="sub-fiscal" class="hidden [.sidebar-oculta_&]:hidden flex flex-col gap-1 pl-4 mt-1 border-l border-white/10 ml-3">
                    <!-- SPED (nível 2, abre lateral) -->
                    <div class="relative">
                        <button id="btn-sped" onclick="toggleFlyout('sub-sped', 'btn-sped')"
                                class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                            <span>SPED</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>

                        <div id="sub-sped" data-flyout data-botao="btn-sped"
                             class="hidden fixed bg-gray-900 border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
                            <a href="{{ route('sped.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Gerar SPED
                            </a>
                            <a href="{{ route('inventarios.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Inventário
                            </a>
                            <a href="{{ route('credenciadoras.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Credenciadoras
                            </a>
                            <a href="{{ route('sped-config.editar') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Configuração do SPED
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif

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
                    'entradas-nota' => ['Faturamento', 'Nota Fiscal - Entrada'],
                    'series-nfe' => ['Faturamento', 'Séries de NF-e'],
                    'mdfes' => ['Faturamento', 'MDF-e'],
                    'sped' => ['Fiscal', 'Gerar SPED'],
                    'sped-config' => ['Fiscal', 'Configuração do SPED'],
                    'inventarios' => ['Fiscal', 'Inventário do SPED'],
                    'credenciadoras' => ['Fiscal', 'Credenciadoras'],
                    'transportadores' => ['Cadastros', 'Transportadoras'],
                    'veiculos' => ['Cadastros', 'Veículos'],
                    'condutores' => ['Cadastros', 'Condutores'],
                    'contas-pagar' => ['Financeiro', 'Contas a Pagar'],
                    'contas-receber' => ['Financeiro', 'Contas a Receber'],
                    'pedidos-compra' => ['Financeiro', 'Pedidos de Compra'],
                    'pedidos-venda' => ['Pedidos', 'Pedido de Venda'],
                    'ordens-servico' => ['Pedidos', 'Ordem de Serviço'],
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
        if (!e.target.closest('[data-menu-acoes-painel]')) fecharMenusAcoes();
        return;
    }

    const painel = botao.parentElement.querySelector('[data-menu-acoes-painel]');
    const estavaFechado = painel.classList.contains('hidden');

    fecharMenusAcoes();
    if (!estavaFechado) return;

    painel.classList.remove('hidden');

    const rect = botao.getBoundingClientRect();
    const altura = painel.offsetHeight;
    const largura = painel.offsetWidth;
    const abrirParaCima = rect.bottom + altura + 8 > window.innerHeight;

    painel.style.top = (abrirParaCima ? rect.top - altura - 4 : rect.bottom + 4) + 'px';
    painel.style.left = Math.max(8, rect.right - largura) + 'px';
});

window.addEventListener('scroll', fecharMenusAcoes, true);
window.addEventListener('resize', fecharMenusAcoes);
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') fecharMenusAcoes();
});

function toggleFlyout(idFlyout, idBotao) {
    const flyout = document.getElementById(idFlyout);
    const botao = document.getElementById(idBotao);
    const estaAbrindo = flyout.classList.contains('hidden');

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

function toggleFinanceiro() {
    if (document.body.classList.contains('sidebar-oculta')) {
        document.body.classList.remove('sidebar-oculta');
    }

    document.getElementById('sub-financeiro').classList.toggle('hidden');
    document.getElementById('seta-financeiro').classList.toggle('rotate-180');
}

function togglePedidos() {
    if (document.body.classList.contains('sidebar-oculta')) {
        document.body.classList.remove('sidebar-oculta');
    }

    document.getElementById('sub-pedidos').classList.toggle('hidden');
    document.getElementById('seta-pedidos').classList.toggle('rotate-180');
}

function toggleRelatorios() {
    if (document.body.classList.contains('sidebar-oculta')) {
        document.body.classList.remove('sidebar-oculta');
    }

    document.getElementById('sub-relatorios').classList.toggle('hidden');
    document.getElementById('seta-relatorios').classList.toggle('rotate-180');
}

function toggleFiscal() {
    if (document.body.classList.contains('sidebar-oculta')) {
        document.body.classList.remove('sidebar-oculta');
    }

    document.getElementById('sub-fiscal').classList.toggle('hidden');
    document.getElementById('seta-fiscal').classList.toggle('rotate-180');
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