@extends('layouts.app')

@section('titulo', 'Transportadoras')

@section('conteudo')
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Transportadoras</h1>
        <a href="{{ route('transportadores.create') }}"
           class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2.5 rounded-lg font-semibold text-sm transition">
            + Nova Transportadora
        </a>
    </div>

    <div class="flex items-center gap-2 mb-4">
        <form method="GET" id="form-busca-transportador" class="flex items-center gap-2">
            <input type="hidden" name="status" id="status-valor" value="{{ $filtro }}">
            <input type="hidden" name="ordenar" id="ordenar-valor" value="{{ $ordenarPor }}">

            <input type="text" name="busca" id="busca-input" value="{{ request('busca') }}" placeholder="Buscar por nome ou CPF/CNPJ..." autocomplete="off"
                   class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm w-72 focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">

            <button type="submit" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 px-4 py-2 rounded-lg text-sm font-medium transition">
                Buscar
            </button>
        </form>

        <div class="flex items-center gap-2 ml-auto">
            <div class="relative">
                <button type="button" onclick="toggleDropdownStatus()" id="btn-status"
                        class="flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 px-4 py-2 rounded-lg text-sm font-medium transition">
                    Status: <span class="font-semibold text-gray-800">{{ match($filtro) { 'ativos' => 'Ativos', 'inativos' => 'Inativos', default => 'Todos' } }}</span>
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <div id="dropdown-status" class="absolute hidden right-0 bg-white rounded-lg shadow-lg mt-2 w-48 overflow-hidden z-20 border border-gray-200">
                    @foreach (['ativos' => 'Ativos', 'inativos' => 'Inativos', 'todos' => 'Todos'] as $valor => $rotulo)
                        <button type="button" onclick="selecionarStatus('{{ $valor }}')"
                                class="w-full text-left flex items-center justify-between px-4 py-2.5 text-sm transition {{ !$loop->first ? 'border-t border-gray-100' : '' }} {{ $filtro === $valor ? 'bg-gray-50 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                            {{ $rotulo }}
                            @if ($filtro === $valor) <span class="text-blue-600">✓</span> @endif
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="relative">
                <button type="button" onclick="toggleDropdownOrdenar()" id="btn-ordenar"
                        class="flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 px-4 py-2 rounded-lg text-sm font-medium transition">
                    Ordenar por: <span id="ordenar-label" class="font-semibold text-gray-800">{{ $ordenarPor === 'documento' ? 'CPF/CNPJ' : 'Nome' }}</span>
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <div id="dropdown-ordenar" class="absolute hidden right-0 bg-white rounded-lg shadow-lg mt-2 w-52 overflow-hidden z-20 border border-gray-200">
                    <button type="button" onclick="selecionarOrdenar('nome', 'Nome')"
                            class="w-full text-left flex items-center justify-between px-4 py-2.5 text-sm transition {{ $ordenarPor === 'nome' ? 'bg-gray-50 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                        Nome
                        @if ($ordenarPor === 'nome') <span class="text-blue-600">✓</span> @endif
                    </button>
                    <button type="button" onclick="selecionarOrdenar('documento', 'CPF/CNPJ')"
                            class="w-full text-left flex items-center justify-between px-4 py-2.5 text-sm transition border-t border-gray-100 {{ $ordenarPor === 'documento' ? 'bg-gray-50 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                        CPF/CNPJ
                        @if ($ordenarPor === 'documento') <span class="text-blue-600">✓</span> @endif
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="resultado-transportadores">
        @include('transportadores._tabela', ['transportadores' => $transportadores])
    </div>

    <script>
        let timeoutBuscaTransportador;

        function toggleDropdownStatus() {
            document.getElementById('dropdown-status').classList.toggle('hidden');
        }

        function toggleDropdownOrdenar() {
            document.getElementById('dropdown-ordenar').classList.toggle('hidden');
        }

        function selecionarStatus(valor) {
            document.getElementById('status-valor').value = valor;
            document.getElementById('dropdown-status').classList.add('hidden');
            buscarTransportadores();
        }

        function selecionarOrdenar(valor, label) {
            document.getElementById('ordenar-valor').value = valor;
            document.getElementById('ordenar-label').innerText = label;
            document.getElementById('dropdown-ordenar').classList.add('hidden');
            buscarTransportadores();
        }

        document.getElementById('busca-input').addEventListener('input', () => {
            clearTimeout(timeoutBuscaTransportador);
            timeoutBuscaTransportador = setTimeout(buscarTransportadores, 300);
        });

        document.getElementById('form-busca-transportador').addEventListener('submit', (e) => {
            e.preventDefault();
            buscarTransportadores();
        });

        async function buscarTransportadores() {
            const params = new URLSearchParams({
                status: document.getElementById('status-valor').value,
                ordenar: document.getElementById('ordenar-valor').value,
                busca: document.getElementById('busca-input').value,
            });

            const url = `{{ route('transportadores.index') }}?${params.toString()}`;

            const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            document.getElementById('resultado-transportadores').innerHTML = await resp.text();

            window.history.replaceState(null, '', url);
        }

        document.addEventListener('click', (e) => {
            ['dropdown-status', 'dropdown-ordenar'].forEach((id) => {
                const el = document.getElementById(id);
                const container = el?.closest('.relative');
                if (el && !el.classList.contains('hidden') && container && !container.contains(e.target)) {
                    el.classList.add('hidden');
                }
            });
        });
    </script>
@endsection