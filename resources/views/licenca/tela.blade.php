@php
    $cores = match ($situacao['estado']) {
        'valida'     => 'border-green-200 bg-green-50 text-green-800',
        'vencendo', 'tolerancia' => 'border-amber-200 bg-amber-50 text-amber-800',
        default      => 'border-red-200 bg-red-50 text-red-700',
    };
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Licença - Cyberly Sistema</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-gray-800 antialiased flex items-center justify-center p-6">
<div class="w-full max-w-xl bg-white rounded-xl shadow-sm border border-gray-200 p-8">
    <p class="text-sm font-semibold text-[#163f86]">Cyberly Sistema</p>
    <h1 class="text-2xl font-bold mt-1">{{ $situacao['bloqueada'] ? 'Sistema bloqueado' : 'Licença do sistema' }}</h1>

    @if (session('sucesso'))
        <div class="mt-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('sucesso') }}</div>
    @else
        <div class="mt-6 rounded-lg border px-4 py-3 text-sm {{ $cores }}">
            {{ $situacao['mensagem'] }}
            @if ($situacao['bloqueada'])
                <br>Entre em contato com o suporte para receber uma nova licença.
            @endif
        </div>
    @endif

    <form method="POST" action="{{ route('licenca.registrar') }}" class="mt-6">
        @csrf
        <label for="codigo" class="block text-sm font-medium text-gray-700">Código da nova licença</label>
        <textarea id="codigo" name="codigo" rows="4" required spellcheck="false"
                  class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-xs focus:border-[#1d5bbf] focus:outline-none"
                  placeholder="CYB1....">{{ old('codigo') }}</textarea>
        @error('codigo')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
        <button type="submit" class="mt-3 rounded-lg bg-[#163f86] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1d5bbf]">
            Registrar licença
        </button>
    </form>

    <div class="mt-8 border-t border-gray-100 pt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm">
        @if ($situacao['bloqueada'])
            {{-- O que continua aberto com o sistema bloqueado --}}
            @auth('web')
                <a href="{{ route('notasfiscais.index') }}" class="text-[#163f86] hover:underline">Consultar notas fiscais (XML/DANFE)</a>
            @endauth
            @auth('caixa')
                <a href="{{ route('caixa.fechar-form') }}" class="text-[#163f86] hover:underline">Fechar o caixa</a>
                <a href="{{ route('contingencias.listar') }}" class="text-[#163f86] hover:underline">NFC-e em contingência</a>
            @endauth
            @guest('web')
                @guest('caixa')
                    <a href="{{ route('auth.escolha') }}" class="text-[#163f86] hover:underline">Entrar</a>
                @endguest
            @endguest
        @else
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('auth.escolha') }}" class="text-[#163f86] hover:underline">Voltar</a>
        @endif
    </div>
</div>
</body>
</html>
