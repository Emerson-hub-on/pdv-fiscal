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
    @include('partials.favicon')
    @include('partials.escape-html')
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
<aside id="nav-principal" class="bg-[#10284A] fixed top-0 left-0 h-screen w-50 flex flex-col z-40 overflow-y-auto transition-[width] duration-200 [.sidebar-oculta_&]:w-16 [.sem-sidebar_&]:hidden">
    <div class="px-3 py-4 border-b border-white/10 shrink-0 flex items-center justify-between [.sidebar-oculta_&]:justify-center">
        <div class="overflow-hidden flex items-center gap-1.5 [.sidebar-oculta_&]:hidden">
            @include('partials.logo-simbolo', ['tamanho' => 26, 'rotulo' => 'Cyberly Sistema'])
            <div class="min-w-0">
                <p class="text-amber-50 font-bold text-sm leading-tight tracking-tight whitespace-nowrap">Cyberly Sistema</p>
                <p class="text-slate-400 text-xs mt-0.5 whitespace-nowrap">Painel administrativo</p>
            </div>
        </div>
        <button type="button" onclick="toggleSidebar()" title="Minimizar/Expandir menu"
                class="shrink-0 flex items-center justify-center w-8 h-8 rounded-lg text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
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
                            class="hidden fixed bg-[#10284A] border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
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

                @if (auth()->user()?->podeVer('ifood'))
                    <a href="{{ route('ifood.index') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                        iFood
                    </a>
                @endif

                @if (auth()->user()?->podeVer('pdvs'))
                    {{-- Caixa: PDVs e as formas de pagamento da tela de pagamento --}}
                    <div class="relative">
                        <button id="btn-cadastro-caixa" onclick="toggleFlyout('sub-cadastro-caixa', 'btn-cadastro-caixa')"
                                class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                            <span>Caixa</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>

                        <div id="sub-cadastro-caixa" data-flyout data-botao="btn-cadastro-caixa"
                            class="hidden fixed bg-[#10284A] border border-white/10 rounded-lg py-1 min-w-48 z-50 shadow-lg">
                            <a href="{{ route('pdvs.index') }}"
                            class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                PDVs
                            </a>
                            <a href="{{ route('formas-pagamento-caixa.index') }}"
                            class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition border-t border-white/10">
                                Formas de pagamento
                            </a>
                        </div>
                    </div>
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
                            class="hidden fixed bg-[#10284A] border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
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

                            <a href="{{ route('usuarios.index', 'garcom') }}"
                            class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition border-t border-white/10">
                                Garçom
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
                        class="hidden fixed bg-[#10284A] border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
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
                    <div id="sub-notafiscal" data-flyout data-botao="btn-notafiscal" class="hidden fixed bg-[#10284A] border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
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

                        <a href="{{ route('notasfiscais.exportar-xml') }}"
                        class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition border-t border-white/10">
                            Exportar XML NF-e
                        </a>
                    </div>

                </div>

                <!-- Montagem NF-e (nível 2, abre lateral) -->
                <div class="relative">
                    <button id="btn-montagem-nfe" onclick="toggleFlyout('sub-montagem-nfe', 'btn-montagem-nfe')"
                            class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                        <span>Montagem NF-e</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>

                    <div id="sub-montagem-nfe" data-flyout data-botao="btn-montagem-nfe"
                        class="hidden fixed bg-[#10284A] border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
                        <a href="{{ route('montagem-nfe.nfce.index') }}"
                        class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                            A partir de NFC-e
                        </a>
                        <a href="{{ route('montagem-nfe.pedido.index') }}"
                        class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition border-t border-white/10">
                            A partir de pedido
                        </a>
                        <a href="{{ route('montagem-nfe.devolucao.index') }}"
                        class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition border-t border-white/10">
                            NF-e Devolução
                        </a>
                        <a href="{{ route('montagem-nfe.troca.index') }}"
                        class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition border-t border-white/10">
                            NF-e de Troca
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
                        class="hidden fixed bg-[#10284A] border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
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
                         class="hidden fixed bg-[#10284A] border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
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
                    <a href="{{ route('creditos-loja.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                    Créditos em loja
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
                             class="hidden fixed bg-[#10284A] border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
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
                            <a href="{{ route('financeiro.relatorios.troca.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Troca
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
                             class="hidden fixed bg-[#10284A] border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
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

        <!-- Menu Dropdown: Comandas -->
        @if (auth()->user()?->podeVer('comandas'))
            <div class="flex flex-col">
                <button onclick="toggleComandas()"
                        class="flex items-center justify-between w-full px-3 py-2.5 rounded-lg text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 text-sm font-medium transition cursor-pointer [.sidebar-oculta_&]:justify-center [.sidebar-oculta_&]:px-0">
                    <span class="flex items-center gap-3">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l2 2 4-4M7 3h10a2 2 0 012 2v16l-3-2-2 2-2-2-2 2-2-2-3 2V5a2 2 0 012-2z"></path>
                        </svg>
                        <span class="whitespace-nowrap [.sidebar-oculta_&]:hidden">Comandas</span>
                    </span>
                    <svg id="seta-comandas" class="w-4 h-4 transition-transform duration-200 [.sidebar-oculta_&]:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <div id="sub-comandas" class="hidden [.sidebar-oculta_&]:hidden flex flex-col gap-1 pl-4 mt-1 border-l border-white/10 ml-3">
                    <!-- Pedidos (nível 2, abre lateral) -->
                    <div class="relative">
                        <button id="btn-comandas-pedidos" onclick="toggleFlyout('sub-comandas-pedidos', 'btn-comandas-pedidos')"
                                class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                            <span>Pedidos</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>

                        <div id="sub-comandas-pedidos" data-flyout data-botao="btn-comandas-pedidos"
                             class="hidden fixed bg-[#10284A] border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
                            <a href="{{ route('comanda-pedidos.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Lista de Pedidos
                            </a>
                        </div>
                    </div>

                    <!-- Cardápio (nível 2, abre lateral) -->
                    <div class="relative">
                        <button id="btn-comandas-cardapio" onclick="toggleFlyout('sub-comandas-cardapio', 'btn-comandas-cardapio')"
                                class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                            <span>Cardápio</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>

                        <div id="sub-comandas-cardapio" data-flyout data-botao="btn-comandas-cardapio"
                             class="hidden fixed bg-[#10284A] border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
                            <a href="{{ route('cardapio.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Menu
                            </a>
                        </div>
                    </div>

                    <!-- Mesas (nível 2, abre lateral) -->
                    <div class="relative">
                        <button id="btn-comandas-mesas" onclick="toggleFlyout('sub-comandas-mesas', 'btn-comandas-mesas')"
                                class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                            <span>Mesas</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>

                        <div id="sub-comandas-mesas" data-flyout data-botao="btn-comandas-mesas"
                             class="hidden fixed bg-[#10284A] border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
                            <a href="{{ route('mesas.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Lista de Mesas
                            </a>
                        </div>
                    </div>

                    <!-- Relatórios (nível 2, abre lateral) -->
                    <div class="relative">
                        <button id="btn-comandas-relatorios" onclick="toggleFlyout('sub-comandas-relatorios', 'btn-comandas-relatorios')"
                                class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                            <span>Relatórios</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>

                        <div id="sub-comandas-relatorios" data-flyout data-botao="btn-comandas-relatorios"
                             class="hidden fixed bg-[#10284A] border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
                            <a href="{{ route('comandas.relatorios.pedidos.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Pedidos
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif

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
                             class="hidden fixed bg-[#10284A] border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
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
                            <a href="{{ route('relatorios.vendas.canceladas.index') }}"
                               class="block px-3 py-2 text-sm text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                                Canceladas
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
                             class="hidden fixed bg-[#10284A] border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
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
                             class="hidden fixed bg-[#10284A] border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
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
                             class="hidden fixed bg-[#10284A] border border-white/10 rounded-lg py-1 min-w-40 z-50 shadow-lg">
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

        <!-- Menu Dropdown: Configuração (só admin) -->
        @if (auth()->user()?->isAdmin())
            <div class="flex flex-col">
                <button onclick="toggleConfiguracao()"
                        class="flex items-center justify-between w-full px-3 py-2.5 rounded-lg text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 text-sm font-medium transition cursor-pointer [.sidebar-oculta_&]:justify-center [.sidebar-oculta_&]:px-0">
                    <span class="flex items-center gap-3">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span class="whitespace-nowrap [.sidebar-oculta_&]:hidden">Configuração</span>
                    </span>
                    <svg id="seta-configuracao" class="w-4 h-4 transition-transform duration-200 [.sidebar-oculta_&]:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <div id="sub-configuracao" class="hidden [.sidebar-oculta_&]:hidden flex flex-col gap-1 pl-4 mt-1 border-l border-white/10 ml-3">
                    <a href="{{ route('licenca.painel') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                        Licença
                    </a>
                    <a href="{{ route('versao.painel') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-amber-50/10 hover:text-amber-50 text-sm transition">
                        Versão
                    </a>
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
    <div id="conteudo-principal" class="ml-50 min-h-screen transition-[margin] duration-200 [.sidebar-oculta_&]:ml-16 [.sem-sidebar_&]:ml-0">
        @include('partials.aviso-licenca', ['faixa' => true, 'rotaLicenca' => auth()->user()?->isAdmin() ? 'licenca.painel' : 'licenca.tela'])

            @php
                // Mapa central do breadcrumb: prefixo da rota => [Grupo, Página]
                $breadcrumbMapa = [
                    'produtos' => ['Cadastros', 'Produtos'],
                    'clientes' => ['Cadastros', 'Clientes'],
                    'empresa'  => ['Cadastros', 'Empresa'],
                    'ifood'    => ['Cadastros', 'iFood'],
                    'pdvs'     => ['Cadastros', 'Caixa', 'PDVs'],
                    'formas-pagamento-caixa' => ['Cadastros', 'Caixa', 'Formas de pagamento'],
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
                    'cardapio' => ['Comandas', 'Cardápio', 'Menu'],
                    'mesas' => ['Comandas', 'Mesas', 'Lista de Mesas'],
                    'comanda-pedidos' => ['Comandas', 'Pedidos', 'Lista de Pedidos'],
                    'licenca' => ['Configuração', 'Licença'],
                    'versao' => ['Configuração', 'Versão'],
                ];

                $rotaAtual = \Illuminate\Support\Facades\Route::currentRouteName();$prefixoRota = $rotaAtual ? explode('.', $rotaAtual)[0] : null;
                $breadcrumbAuto = $breadcrumbMapa[$prefixoRota] ?? null;
            @endphp

        {{-- Cabeçalho do topo (do menu até a borda direita): caminho da tela à esquerda, perfil do usuário à direita --}}
        {{-- Azul-marinho com textos claros; as telas que montam o próprio caminho (@section breadcrumb) usam
             text-gray-300/700, que aqui dentro viram tons claros --}}
        <header class="h-14 px-6 bg-[#10284A] border-b border-white/10 shadow-sm flex items-center justify-between gap-4">
            <div class="flex items-center gap-2 min-w-0 text-sm text-slate-300 [&_.text-gray-300]:text-slate-400 [&_.text-gray-700]:text-white">
                @hasSection('breadcrumb')
                    @yield('breadcrumb')
                @elseif ($breadcrumbAuto)
                    @foreach ($breadcrumbAuto as $parte)
                        @if ($loop->last)
                            <span class="text-white font-medium truncate">{{ $parte }}</span>
                        @else
                            <span>{{ $parte }}</span>
                            <span class="text-slate-400">›</span>
                        @endif
                    @endforeach
                @endif
            </div>
            <div class="[.sem-sidebar_&]:hidden">
                @include('partials.perfil-usuario')
            </div>
        </header>

        <main class="max-w-5xl mx-auto p-8 [.conteudo-largo_&]:max-w-none [.conteudo-largo_&]:mx-0 [.conteudo-largo_&]:px-6 [.conteudo-largo_&]:pt-4">

            @if (session('sucesso'))
                {{-- Aviso flutuante: some em 5 s, ao clicar em qualquer lugar ou ao trocar de tela --}}
                <div id="toast-sucesso" role="status"
                     class="fixed top-16 right-4 z-50 flex items-start gap-3 max-w-sm bg-white border border-green-200 shadow-lg rounded-lg px-4 py-3 text-sm text-gray-800 transition-all duration-300">
                    <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <span class="flex-1">{{ session('sucesso') }}</span>
                    <button type="button" aria-label="Fechar" class="text-gray-400 hover:text-gray-600 leading-none">&times;</button>
                </div>
                <script>
                    (function () {
                        const toast = document.getElementById('toast-sucesso');
                        const fechar = () => {
                            if (!toast.isConnected) return;
                            toast.classList.add('opacity-0', 'translate-x-4');
                            setTimeout(() => toast.remove(), 300);
                        };
                        setTimeout(fechar, 5000);
                        document.addEventListener('click', fechar, { once: true, capture: true });
                        window.addEventListener('pagehide', () => toast.remove(), { once: true });
                    })();
                </script>
            @endif

            @if ($errors->any() && !View::hasSection('erros-no-formulario'))
                {{-- Erros de validação: aviso flutuante. Some em 8 s (pausa com o mouse em cima), no X ou ao trocar de tela.
                     Não fecha com qualquer clique: o operador lê enquanto clica no campo para corrigir.
                     Telas que mostram os erros do seu jeito (janela própria) declaram @section('erros-no-formulario', '1'). --}}
                <div id="toast-erros" role="alert"
                     class="fixed {{ session('sucesso') ? 'top-36' : 'top-16' }} right-4 z-50 flex items-start gap-3 w-full max-w-md bg-white border border-red-200 border-l-4 border-l-red-500 shadow-lg rounded-lg px-4 py-3 text-sm text-gray-800 transition-all duration-300">
                    <svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-red-700">{{ $errors->count() > 1 ? 'Corrija os erros:' : 'Corrija o erro:' }}</p>
                        <ul class="mt-1 space-y-0.5 text-red-700 {{ $errors->count() > 1 ? 'list-disc pl-4' : '' }}">
                            @foreach ($errors->all() as $erro)
                                <li>{{ $erro }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <button type="button" aria-label="Fechar" class="text-gray-400 hover:text-gray-600 text-lg leading-none cursor-pointer">&times;</button>
                </div>
                <script>
                    (function () {
                        const toast = document.getElementById('toast-erros');
                        let timer;
                        const fechar = () => {
                            if (!toast.isConnected) return;
                            toast.classList.add('opacity-0', 'translate-x-4');
                            setTimeout(() => toast.remove(), 300);
                        };
                        const agendar = () => { clearTimeout(timer); timer = setTimeout(fechar, 8000); };
                        agendar();
                        toast.addEventListener('mouseenter', () => clearTimeout(timer));
                        toast.addEventListener('mouseleave', agendar);
                        toast.querySelector('button').addEventListener('click', fechar);
                        window.addEventListener('pagehide', () => toast.remove(), { once: true });
                    })();
                </script>
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

function toggleComandas() {
    if (document.body.classList.contains('sidebar-oculta')) {
        document.body.classList.remove('sidebar-oculta');
    }

    document.getElementById('sub-comandas').classList.toggle('hidden');
    document.getElementById('seta-comandas').classList.toggle('rotate-180');
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

function toggleConfiguracao() {
    if (document.body.classList.contains('sidebar-oculta')) {
        document.body.classList.remove('sidebar-oculta');
    }

    document.getElementById('sub-configuracao').classList.toggle('hidden');
    document.getElementById('seta-configuracao').classList.toggle('rotate-180');
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
</script>
@include('partials.sincronizacao')
</body>
</html>