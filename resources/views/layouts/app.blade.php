<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo', 'PDV Fiscal')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-800">

<!-- Sidebar corrigido para altura total fixa com h-screen -->
<aside id="nav-principal" class="bg-gray-800 fixed top-0 left-0 h-screen w-56 flex flex-col z-40 overflow-y-auto">
    <div class="px-6 py-5 border-b border-white/10 shrink-0">
        <p class="text-white font-bold text-lg tracking-tight">PDV Fiscal</p>
        <p class="text-slate-400 text-xs mt-0.5">Painel administrativo</p>
    </div>

    <nav class="flex flex-col gap-1 px-3 py-4 flex-1">
        <a href="{{ route('produtos.index') }}"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-white/10 hover:text-white text-sm font-medium transition">
            Produtos
        </a>
        <a href="{{ route('empresa.editar') }}"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-white/10 hover:text-white text-sm font-medium transition">
            Empresa
        </a>
        <a href="{{ route('pdvs.index') }}"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-white/10 hover:text-white text-sm font-medium transition">
            PDVs
        </a>
    </nav>

    <div class="px-3 py-4 border-t border-white/10 shrink-0">
        <button onclick="sincronizarAgora()" id="btn-sincronizar"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-white/10 hover:text-white text-sm font-medium transition cursor-pointer">
            Atualizar Caixa
        </button>
    </div>
</aside>

    <!-- Conteúdo principal -->
    <div id="conteudo-principal" class="ml-56 min-h-screen">
        <main class="max-w-5xl mx-auto p-8">
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