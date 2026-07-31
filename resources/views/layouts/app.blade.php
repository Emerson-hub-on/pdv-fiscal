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
</body>
</html>