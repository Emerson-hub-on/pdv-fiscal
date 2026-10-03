<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso negado</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow w-full max-w-md text-center">
        <h1 class="text-xl font-bold mb-2">Acesso negado</h1>
        <p class="text-sm text-gray-600 mb-6">
            {{ $exception->getMessage() ?: 'Você não tem permissão para acessar esta área.' }}
        </p>
        <a href="javascript:history.back()" class="inline-block bg-gray-800 hover:bg-gray-700 text-white px-5 py-2 rounded-lg text-sm font-medium">
            Voltar
        </a>
    </div>
</body>
</html>