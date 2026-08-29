@extends('layouts.app')

@section('titulo', 'Produtos')


@section('conteudo')
    <div class="flex justify-end items-center mb-6">
        <a href="{{ route('produtos.create') }}"
           class=" bg-blue-600 hover:bg-blue-500 text-white px-4 py-2.5 rounded-lg font-semibold text-sm transition">
            + Novo Produto
        </a>
    </div>

    @if (session('sucesso'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
            {{ session('sucesso') }}
        </div>
    @endif

    <div class="flex items-center gap-2 mb-4">
        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="status" value="{{ $filtro }}">
            <input type="hidden" name="ordenar" value="{{ $ordenarPor }}">
            <input type="hidden" name="tipo_busca" id="tipo-busca-valor" value="{{ $tipoBusca }}">

            <div class="relative">
                <button type="button" onclick="toggleDropdownTipoBusca()" id="btn-tipo-busca"
                        class="flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 px-4 py-2 rounded-lg text-sm font-medium transition">
                    <span id="tipo-busca-label">{{ match($tipoBusca) { 'codigo_interno' => 'Código interno', 'codigo_barras' => 'Código de barras', default => 'Nome' } }}</span>
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <div id="dropdown-tipo-busca" class="absolute hidden left-0 bg-white rounded-lg shadow-lg mt-2 w-48 overflow-hidden z-20 border border-gray-200">
                    <button type="button" onclick="selecionarTipoBusca('nome', 'Nome')"
                            class="w-full text-left flex items-center justify-between px-4 py-2.5 text-sm transition {{ $tipoBusca === 'nome' ? 'bg-gray-50 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                        Nome
                        @if ($tipoBusca === 'nome')
                            <span class="text-blue-600">✓</span>
                        @endif
                    </button>
                    <button type="button" onclick="selecionarTipoBusca('codigo_interno', 'Código interno')"
                            class="w-full text-left flex items-center justify-between px-4 py-2.5 text-sm transition border-t border-gray-100 {{ $tipoBusca === 'codigo_interno' ? 'bg-gray-50 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                        Código interno
                        @if ($tipoBusca === 'codigo_interno')
                            <span class="text-blue-600">✓</span>
                        @endif
                    </button>
                    <button type="button" onclick="selecionarTipoBusca('codigo_barras', 'Código de barras')"
                            class="w-full text-left flex items-center justify-between px-4 py-2.5 text-sm transition border-t border-gray-100 {{ $tipoBusca === 'codigo_barras' ? 'bg-gray-50 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                        Código de barras
                        @if ($tipoBusca === 'codigo_barras')
                            <span class="text-blue-600">✓</span>
                        @endif
                    </button>
                </div>
            </div>

            <input type="text" name="busca" value="{{ $busca }}" placeholder="Buscar..."
                   class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm w-64 focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">

            <button type="submit"
                    class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 px-4 py-2 rounded-lg text-sm font-medium transition">
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
                    <a href="{{ route('produtos.index', ['status' => 'ativos', 'ordenar' => $ordenarPor, 'busca' => $busca, 'tipo_busca' => $tipoBusca]) }}"
                       class="flex items-center justify-between px-4 py-2.5 text-sm transition {{ $filtro === 'ativos' ? 'bg-gray-50 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                        Ativos
                        @if ($filtro === 'ativos')
                            <span class="text-blue-600">✓</span>
                        @endif
                    </a>
                    <a href="{{ route('produtos.index', ['status' => 'inativos', 'ordenar' => $ordenarPor, 'busca' => $busca, 'tipo_busca' => $tipoBusca]) }}"
                       class="flex items-center justify-between px-4 py-2.5 text-sm transition border-t border-gray-100 {{ $filtro === 'inativos' ? 'bg-gray-50 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                        Inativos
                        @if ($filtro === 'inativos')
                            <span class="text-blue-600">✓</span>
                        @endif
                    </a>
                    <a href="{{ route('produtos.index', ['status' => 'todos', 'ordenar' => $ordenarPor, 'busca' => $busca, 'tipo_busca' => $tipoBusca]) }}"
                       class="flex items-center justify-between px-4 py-2.5 text-sm transition border-t border-gray-100 {{ $filtro === 'todos' ? 'bg-gray-50 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                        Todos
                        @if ($filtro === 'todos')
                            <span class="text-blue-600">✓</span>
                        @endif
                    </a>
                </div>
            </div>

            <div class="relative">
                <button type="button" onclick="toggleDropdownOrdenar()" id="btn-ordenar"
                        class="flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 px-4 py-2 rounded-lg text-sm font-medium transition">
                    Ordenar por: <span class="font-semibold text-gray-800">{{ $ordenarPor === 'codigo' ? 'Código' : 'Ordem alfabética' }}</span>
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <div id="dropdown-ordenar" class="absolute hidden right-0 bg-white rounded-lg shadow-lg mt-2 w-52 overflow-hidden z-20 border border-gray-200">
                    <a href="{{ route('produtos.index', ['status' => $filtro, 'ordenar' => 'nome', 'busca' => $busca, 'tipo_busca' => $tipoBusca]) }}"
                       class="flex items-center justify-between px-4 py-2.5 text-sm transition {{ $ordenarPor === 'nome' ? 'bg-gray-50 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                        Ordem alfabética
                        @if ($ordenarPor === 'nome')
                            <span class="text-blue-600">✓</span>
                        @endif
                    </a>
                    <a href="{{ route('produtos.index', ['status' => $filtro, 'ordenar' => 'codigo', 'busca' => $busca, 'tipo_busca' => $tipoBusca]) }}"
                       class="flex items-center justify-between px-4 py-2.5 text-sm transition border-t border-gray-100 {{ $ordenarPor === 'codigo' ? 'bg-gray-50 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                        Código
                        @if ($ordenarPor === 'codigo')
                            <span class="text-blue-600">✓</span>
                        @endif
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-600 rounded-lg font-medium  text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Nome</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Código</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Cód. Barras</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Preço</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Estoque</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($produtos as $produto)
                    <tr class="hover:bg-gray-200 transition">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $produto->nome }}</td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $produto->codigo_interno }}</td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $produto->codigo_barras ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">R$ {{ number_format($produto->preco_venda, 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $produto->estoque_total }}</td>
                        <td class="px-4 py-3">
                            @if ($produto->ativo)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                    Ativo
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-600">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                    Inativo
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('produtos.edit', $produto) }}" class="text-blue-600 hover:text-blue-700 font-medium ">Editar</a>
                                <form action="{{ route('produtos.toggle-ativo', $produto) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-orange-600 hover:text-orange-700 font-medium">
                                        {{ $produto->ativo ? 'Inativar' : 'Reativar' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhum produto encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $produtos->links() }}
    </div>

    <script>
        function toggleDropdownStatus() {
            document.getElementById('dropdown-status').classList.toggle('hidden');
        }

        function toggleDropdownOrdenar() {
            document.getElementById('dropdown-ordenar').classList.toggle('hidden');
        }

        function toggleDropdownTipoBusca() {
            document.getElementById('dropdown-tipo-busca').classList.toggle('hidden');
        }

        function selecionarTipoBusca(valor, label) {
            document.getElementById('tipo-busca-valor').value = valor;
            document.getElementById('tipo-busca-label').innerText = label;
            document.getElementById('dropdown-tipo-busca').classList.add('hidden');
        }

        document.addEventListener('click', (e) => {
            const dropdowns = [
                document.getElementById('dropdown-status'),
                document.getElementById('dropdown-ordenar'),
                document.getElementById('dropdown-tipo-busca'),
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