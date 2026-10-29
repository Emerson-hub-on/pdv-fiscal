@extends('layouts.app')

@section('titulo', 'Credenciadoras')

@section('conteudo')
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Credenciadoras</h1>
            <p class="text-sm text-gray-500 mt-1">Bancos e instituições que recebem os pagamentos eletrônicos (cartão, PIX...). Usadas no registro 1601 do SPED.</p>
        </div>
        <a href="{{ route('credenciadoras.create') }}"
           class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2.5 rounded-lg font-semibold text-sm transition whitespace-nowrap">
            + Nova Credenciadora
        </a>
    </div>

    @if (session('sucesso'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">{{ session('sucesso') }}</div>
    @endif

    <div class="flex items-center gap-2 mb-4">
        <form method="GET" id="form-busca" class="flex items-center gap-2">
            <input type="hidden" name="status" id="status-valor" value="{{ $filtro }}">
            <input type="text" name="busca" id="busca-input" value="{{ request('busca') }}" placeholder="Buscar por nome ou CNPJ..." autocomplete="off"
                   class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm w-72 focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
            <button type="submit" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 px-4 py-2 rounded-lg text-sm font-medium transition">
                Buscar
            </button>
        </form>

        <div class="flex items-center gap-1 ml-auto bg-white border border-gray-300 rounded-lg p-1">
            @foreach (['ativas' => 'Ativas', 'inativas' => 'Inativas', 'todas' => 'Todas'] as $valor => $rotulo)
                <button type="button" onclick="selecionarStatus('{{ $valor }}')" data-status="{{ $valor }}"
                        class="px-3 py-1.5 rounded-md text-sm transition {{ $filtro === $valor ? 'bg-gray-700 text-amber-50' : 'text-gray-600 hover:bg-gray-50' }}">
                    {{ $rotulo }}
                </button>
            @endforeach
        </div>
    </div>

    <div id="resultado">
        @include('credenciadoras._tabela', ['credenciadoras' => $credenciadoras])
    </div>

    <script>
        let timeoutBusca;

        function selecionarStatus(valor) {
            document.getElementById('status-valor').value = valor;
            document.querySelectorAll('[data-status]').forEach((b) => {
                const ativo = b.dataset.status === valor;
                b.classList.toggle('bg-gray-700', ativo);
                b.classList.toggle('text-amber-50', ativo);
                b.classList.toggle('text-gray-600', !ativo);
            });
            buscar();
        }

        document.getElementById('busca-input').addEventListener('input', () => {
            clearTimeout(timeoutBusca);
            timeoutBusca = setTimeout(buscar, 300);
        });

        document.getElementById('form-busca').addEventListener('submit', (e) => { e.preventDefault(); buscar(); });

        async function buscar() {
            const params = new URLSearchParams({
                status: document.getElementById('status-valor').value,
                busca: document.getElementById('busca-input').value,
            });
            const url = `{{ route('credenciadoras.index') }}?${params}`;
            const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            document.getElementById('resultado').innerHTML = await resp.text();
            window.history.replaceState(null, '', url);
        }
    </script>
@endsection
