@extends('layouts.app')

@section('titulo', $cfg['titulo'])

@section('breadcrumb')
    <span>Cadastros</span>
    <span class="text-gray-300">›</span>
    <span>Usuários</span>
    <span class="text-gray-300">›</span>
    <span class="text-gray-700 font-medium">{{ $cfg['singular'] }}</span>
@endsection

@section('conteudo')
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">{{ $cfg['titulo'] }}</h1>
        <a href="{{ route('usuarios.create', $perfil) }}"
           class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2.5 rounded-lg font-semibold text-sm transition">
            + Novo {{ $cfg['singular'] }}
        </a>
    </div>

    <form id="form-busca-usuario" class="mb-4">
        <input type="text" id="busca-input" value="{{ request('busca') }}" placeholder="Buscar por nome..." autocomplete="off"
               class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm w-72 focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
    </form>

    <div id="resultado-usuarios">
        @include('usuarios._tabela')
    </div>

    <script>
        let timeoutBuscaUsuario;

        async function buscarUsuarios() {
            const url = `{{ route('usuarios.index', $perfil) }}?busca=${encodeURIComponent(document.getElementById('busca-input').value)}`;
            const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            document.getElementById('resultado-usuarios').innerHTML = await resp.text();
            window.history.replaceState(null, '', url);
        }

        document.getElementById('busca-input').addEventListener('input', () => {
            clearTimeout(timeoutBuscaUsuario);
            timeoutBuscaUsuario = setTimeout(buscarUsuarios, 300);
        });

        document.getElementById('form-busca-usuario').addEventListener('submit', (e) => {
            e.preventDefault();
            buscarUsuarios();
        });
    </script>
@endsection