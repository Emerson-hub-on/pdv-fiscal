@extends('layouts.app')

@section('titulo', 'MDF-e')

@section('conteudo')
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">MDF-e</h1>
        <a href="{{ route('mdfes.create') }}"
           class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2.5 rounded-lg font-semibold text-sm transition">
            + Novo MDF-e
        </a>
    </div>

    @if (session('sucesso'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">{{ session('sucesso') }}</div>
    @endif

    <div class="flex items-center gap-2 mb-4">
        <form method="GET" id="form-busca-mdfe" class="flex items-center gap-2">
            <input type="hidden" name="status" id="status-valor" value="{{ $filtro }}">

            <input type="text" name="busca" id="busca-input" value="{{ request('busca') }}" placeholder="Buscar por número ou chave..." autocomplete="off"
                   class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm w-72 focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">

            <button type="submit" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 px-4 py-2 rounded-lg text-sm font-medium transition">
                Buscar
            </button>
        </form>

        <div class="flex items-center gap-2 ml-auto">
            <div class="relative">
                <button type="button" onclick="toggleDropdown('dropdown-status')"
                        class="flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 px-4 py-2 rounded-lg text-sm font-medium transition">
                    Status: <span class="font-semibold text-gray-800">{{ ['rascunho' => 'Rascunho', 'emitido' => 'Emitido', 'encerrado' => 'Encerrado', 'cancelado' => 'Cancelado'][$filtro] ?? 'Todos' }}</span>
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <div id="dropdown-status" class="absolute hidden right-0 bg-white rounded-lg shadow-lg mt-2 w-48 overflow-hidden z-20 border border-gray-200">
                    @foreach (['todos' => 'Todos', 'rascunho' => 'Rascunho', 'emitido' => 'Emitido', 'encerrado' => 'Encerrado', 'cancelado' => 'Cancelado'] as $valor => $rotulo)
                        <button type="button" onclick="selecionarStatus('{{ $valor }}')"
                                class="w-full text-left flex items-center justify-between px-4 py-2.5 text-sm transition {{ !$loop->first ? 'border-t border-gray-100' : '' }} {{ $filtro === $valor ? 'bg-gray-50 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                            {{ $rotulo }}
                            @if ($filtro === $valor) <span class="text-blue-600">✓</span> @endif
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div id="resultado-mdfes">
        @include('mdfes._tabela', ['mdfes' => $mdfes])
    </div>

    <script>
        let timeoutBuscaMdfe;

        function toggleDropdown(id) {
            document.getElementById(id).classList.toggle('hidden');
        }

        function selecionarStatus(valor) {
            document.getElementById('status-valor').value = valor;
            document.getElementById('dropdown-status').classList.add('hidden');
            buscarMdfes();
        }

        document.getElementById('busca-input').addEventListener('input', () => {
            clearTimeout(timeoutBuscaMdfe);
            timeoutBuscaMdfe = setTimeout(buscarMdfes, 300);
        });

        document.getElementById('form-busca-mdfe').addEventListener('submit', (e) => {
            e.preventDefault();
            buscarMdfes();
        });

        async function buscarMdfes() {
            const params = new URLSearchParams({
                status: document.getElementById('status-valor').value,
                busca: document.getElementById('busca-input').value,
            });

            const url = `{{ route('mdfes.index') }}?${params.toString()}`;

            const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            document.getElementById('resultado-mdfes').innerHTML = await resp.text();

            window.history.replaceState(null, '', url);
        }

        document.addEventListener('click', (e) => {
            const el = document.getElementById('dropdown-status');
            const container = el?.closest('.relative');
            if (el && !el.classList.contains('hidden') && container && !container.contains(e.target)) {
                el.classList.add('hidden');
            }
        });
    </script>
@endsection
