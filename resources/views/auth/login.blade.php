<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - {{ $modo === 'admin' ? 'Administrador' : 'Operador' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow w-full max-w-sm">
        <h1 class="text-xl font-bold mb-6 text-center">
            Login {{ $modo === 'admin' ? 'Administrador' : 'Operador' }}
        </h1>

        @if ($errors->any())
            <div class="bg-red-100 text-red-700 border border-red-300 rounded px-4 py-2 mb-4 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('auth.login.submit') }}" method="POST">
            @csrf
            <input type="hidden" name="modo" value="{{ $modo }}">

            <label class="block text-sm font-medium mb-1">Usuário</label>
            <input type="text" name="username" value="admin" required
                   class="w-full border rounded px-3 py-2 mb-4">

            <label class="block text-sm font-medium mb-1">Senha</label>
            <input type="password" name="password" required
                   class="w-full border rounded px-3 py-2 mb-6">

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded font-semibold">
                Entrar
            </button>
        </form>

        <a href="{{ route('auth.escolha') }}" class="block text-center text-sm text-gray-500 mt-4 hover:underline">
            ← Voltar
        </a>
    </div>
</body>
</html>