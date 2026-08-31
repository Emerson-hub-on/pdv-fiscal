@extends('layouts.app')

@section('titulo', 'Clientes')

@section('conteudo')
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Clientes</h1>
        <a href="{{ route('clientes.create') }}"
           class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2.5 rounded-lg font-semibold text-sm transition">
            + Novo Cliente
        </a>
    </div>

    @if (session('sucesso'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
            {{ session('sucesso') }}
        </div>
    @endif

    <div class="flex items-center gap-2 mb-4">
        <form method="GET" id="form-busca-cliente" class="flex items-center gap-2">
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
                    <button type="button" onclick="selecionarStatus('ativos')"
                            class="w-full text-left flex items-center justify-between px-4 py-2.5 text-sm transition {{ $filtro === 'ativos' ? 'bg-gray-50 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                        Ativos
                        @if ($filtro === 'ativos')
                            <span class="text-blue-600">✓</span>
                        @endif
                    </button>
                    <button type="button" onclick="selecionarStatus('inativos')"
                            class="w-full text-left flex items-center justify-between px-4 py-2.5 text-sm transition border-t border-gray-100 {{ $filtro === 'inativos' ? 'bg-gray-50 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                        Inativos
                        @if ($filtro === 'inativos')
                            <span class="text-blue-600">✓</span>
                        @endif
                    </button>
                    <button type="button" onclick="selecionarStatus('todos')"
                            class="w-full text-left flex items-center justify-between px-4 py-2.5 text-sm transition border-t border-gray-100 {{ $filtro === 'todos' ? 'bg-gray-50 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                        Todos
                        @if ($filtro === 'todos')
                            <span class="text-blue-600">✓</span>
                        @endif
                    </button>
                </div>
            </div>

            <div class="relative">
                <button type="button" onclick="toggleDropdownOrdenar()" id="btn-ordenar"
                        class="flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 px-4 py-2 rounded-lg text-sm font-medium transition">
                    Ordenar por: <span id="ordenar-label" class="font-semibold text-gray-800">{{ $ordenarPor === 'cpf_cnpj' ? 'CPF/CNPJ' : 'Nome' }}</span>
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <div id="dropdown-ordenar" class="absolute hidden right-0 bg-white rounded-lg shadow-lg mt-2 w-52 overflow-hidden z-20 border border-gray-200">
                    <button type="button" onclick="selecionarOrdenar('nome', 'Nome')"
                            class="w-full text-left flex items-center justify-between px-4 py-2.5 text-sm transition {{ $ordenarPor === 'nome' ? 'bg-gray-50 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                        Nome
                        @if ($ordenarPor === 'nome')
                            <span class="text-blue-600">✓</span>
                        @endif
                    </button>
                    <button type="button" onclick="selecionarOrdenar('cpf_cnpj', 'CPF/CNPJ')"
                            class="w-full text-left flex items-center justify-between px-4 py-2.5 text-sm transition border-t border-gray-100 {{ $ordenarPor === 'cpf_cnpj' ? 'bg-gray-50 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                        CPF/CNPJ
                        @if ($ordenarPor === 'cpf_cnpj')
                            <span class="text-blue-600">✓</span>
                        @endif
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="resultado-clientes">
        @include('clientes._tabela', ['clientes' => $clientes])
    </div>

    <script>
        let timeoutBuscaCliente;

        function toggleDropdownStatus() {
            document.getElementById('dropdown-status').classList.toggle('hidden');
        }

        function toggleDropdownOrdenar() {
            document.getElementById('dropdown-ordenar').classList.toggle('hidden');
        }

        function selecionarStatus(valor) {
            document.getElementById('status-valor').value = valor;
            document.getElementById('dropdown-status').classList.add('hidden');
            buscarClientes();
        }

        function selecionarOrdenar(valor, label) {
            document.getElementById('ordenar-valor').value = valor;
            document.getElementById('ordenar-label').innerText = label;
            document.getElementById('dropdown-ordenar').classList.add('hidden');
            buscarClientes();
        }

        document.getElementById('busca-input').addEventListener('input', () => {
            clearTimeout(timeoutBuscaCliente);
            timeoutBuscaCliente = setTimeout(buscarClientes, 300);
        });

        document.getElementById('form-busca-cliente').addEventListener('submit', (e) => {
            e.preventDefault();
            buscarClientes();
        });

        async function buscarClientes() {
            const params = new URLSearchParams({
                status: document.getElementById('status-valor').value,
                ordenar: document.getElementById('ordenar-valor').value,
                busca: document.getElementById('busca-input').value,
            });

            const url = `{{ route('clientes.index') }}?${params.toString()}`;

            const resp = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            const html = await resp.text();
            document.getElementById('resultado-clientes').innerHTML = html;

            window.history.replaceState(null, '', url);
        }

        document.addEventListener('click', (e) => {
            const dropdowns = [
                document.getElementById('dropdown-status'),
                document.getElementById('dropdown-ordenar'),
            ];

            dropdowns.forEach((el) => {
                const container = el?.closest('.relative');
                if (el && !el.classList.contains('hidden') && container && !container.contains(e.target)) {
                    el.classList.add('hidden');
                }
            });
        });
    </script>
@endsection