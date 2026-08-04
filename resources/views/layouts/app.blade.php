<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo', 'PDV Fiscal')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-800">
    <nav class="bg-gray-900 text-white px-6 py-4 flex gap-6">
        <a href="{{ route('produtos.index') }}" class="hover:text-blue-300">Produtos</a>
        <a href="{{ route('empresa.editar') }}" class="hover:text-blue-300">Empresa</a>
        <a href="{{ route('pdvs.index') }}" class="hover:text-blue-300">PDVs</a>
        <button onclick="sincronizarAgora()" id="btn-sincronizar" class="hover:text-blue-300 bg-transparent border-0 text-white cursor-pointer">
            Atualizar Caixa
        </button>
        {{-- outros links de menu vão entrar aqui conforme criarmos os módulos --}}
    </nav>

    <main class="max-w-6xl mx-auto p-6">
        @if (session('sucesso'))
            <div class="bg-green-100 text-green-800 border border-green-300 rounded px-4 py-3 mb-4">
                {{ session('sucesso') }}
            </div>
        @endif

        @yield('conteudo')
    </main>
    @yield('scripts')
</body>
</html>
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