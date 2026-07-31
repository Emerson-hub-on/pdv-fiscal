<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDV Fiscal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="text-center">
        <h1 class="text-3xl font-bold mb-10">Bem-vindo</h1>
        <div class="flex gap-6">
            <a href="{{ route('auth.login', ['modo' => 'admin']) }}"
               class="bg-blue-600 hover:bg-blue-700 text-white px-10 py-8 rounded-lg text-xl font-semibold shadow">
                Sou Admin
            </a>
            <a href="{{ route('auth.login', ['modo' => 'operador']) }}"
               class="bg-green-600 hover:bg-green-700 text-white px-10 py-8 rounded-lg text-xl font-semibold shadow">
                Sou Operador
            </a>
        </div>
    </div>
</body>
</html>