<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo', 'PDV Fiscal')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-800 @yield('body-class')">

<!-- Sidebar corrigido para altura total fixa com h-screen -->
<aside id="nav-principal" class="bg-gray-800 fixed top-0 left-0 h-screen w-50 flex flex-col z-40 overflow-y-auto [.sidebar-oculta_&]:hidden">
    <div class="px-6 py-5 border-b border-white/10 shrink-0">
        <p class="text-white font-bold text-lg tracking-tight">PDV Fiscal</p>
        <p class="text-slate-400 text-xs mt-0.5">Painel administrativo</p>
    </div>

    <nav class="flex flex-col gap-1 px-3 py-4 flex-1 overflow-y-auto">
        <!-- Menu Dropdown: Cadastros -->
        <div class="flex flex-col">
            <button onclick="toggleCadastros()" 
                    class="flex items-center justify-between w-full px-3 py-2.5 rounded-lg text-slate-300 hover:bg-white/10 hover:text-white text-sm font-medium transition cursor-pointer">
                <span class="flex items-center gap-3">Cadastros</span>
                <!-- Seta indicativa -->
                <svg id="seta-cadastros" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            <!-- Subopções (inicialmente ocultas com 'hidden') -->
            <div id="sub-cadastros" class="hidden flex flex-col gap-1 pl-4 mt-1 border-l border-white/10 ml-3">
                <a href="{{ route('produtos.index') }}"
                class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-white/10 hover:text-white text-sm transition">
                    Produtos
                </a>
                <a href="{{ route('clientes.index') }}"
                class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-white/10 hover:text-white text-sm transition">
                    Clientes
                </a>
                <a href="{{ route('empresa.editar') }}"
                class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-white/10 hover:text-white text-sm transition">
                    Empresa
                </a>
                <a href="{{ route('pdvs.index') }}"
                class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-white/10 hover:text-white text-sm transition">
                    PDVs
                </a>
            </div>
        </div>
    </nav>

    <div class="px-3 py-4 border-t border-white/10 shrink-0">
        <button onclick="sincronizarAgora()" id="btn-sincronizar"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-white/10 hover:text-white text-sm font-medium transition cursor-pointer">
            Atualizar Caixa
        </button>
    </div>
</aside>

    <!-- Conteúdo principal -->
    <div id="conteudo-principal" class="ml-56 min-h-screen [.sidebar-oculta_&]:ml-0">
        <main class="max-w-5xl mx-auto p-8 [.sidebar-oculta_&]:max-w-none [.sidebar-oculta_&]:mx-0 [.sidebar-oculta_&]:p-0">

            @php
                // Mapa central do breadcrumb: prefixo da rota => [Grupo, Página]
                // Toda tela nova sob "Cadastros" (ou outro grupo) só precisa de uma linha aqui.
                $breadcrumbMapa = [
                    'produtos' => ['Cadastros', 'Produtos'],
                    'clientes' => ['Cadastros', 'Clientes'],
                    'empresa'  => ['Cadastros', 'Empresa'],
                    'pdvs'     => ['Cadastros', 'PDVs'],
                ];

                $rotaAtual = \Illuminate\Support\Facades\Route::currentRouteName();
                $prefixoRota = $rotaAtual ? explode('.', $rotaAtual)[0] : null;
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

    function toggleCadastros() {
    const subMenu = document.getElementById('sub-cadastros');
    const seta = document.getElementById('seta-cadastros');
    
    // Alterna a classe 'hidden' do Tailwind para mostrar/esconder
    subMenu.classList.toggle('hidden');
    
    // Gira a setinha para indicar aberto/fechado
    seta.classList.toggle('rotate-180');
    }   
    
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
                    (resultado.vendas_falhas > 0 ? `\nVendas com falha: ${resultado.vendas_falhas}` : '')
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