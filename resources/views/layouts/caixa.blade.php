<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo', 'Cyberly Sistema')</title>
    @include('partials.favicon')
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

@include('partials.sincronizacao')
</body>
</html>