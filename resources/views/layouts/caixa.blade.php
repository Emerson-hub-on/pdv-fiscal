<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo', 'PDV Fiscal')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen relative">

<!-- Mesmo fundo dividido do layout admin -->
<div class="absolute inset-0 flex flex-col -z-10 pointer-events-none">
    <div class="w-full h-2/5 bg-gray-200"></div>
    <div class="w-full h-3/5 bg-white"></div>
</div>

<main>
    @yield('conteudo')
</main>

@include('partials.aviso-licenca')

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