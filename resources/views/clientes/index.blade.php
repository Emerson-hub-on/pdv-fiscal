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
        <a href="{{ route('clientes.index', ['status' => 'ativos']) }}"
           class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $filtro === 'ativos' ? 'bg-blue-600 hover:bg-blue-500 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50' }}">
            Ativos
        </a>
        <a href="{{ route('clientes.index', ['status' => 'inativos']) }}"
           class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $filtro === 'inativos' ? 'bg-blue-600 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50' }}">
            Inativos
        </a>
        <a href="{{ route('clientes.index', ['status' => 'todos']) }}"
           class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $filtro === 'todos' ? 'bg-blue-600 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50' }}">
            Todos
        </a>

        <form method="GET" class="flex gap-2 ml-auto">
            <input type="hidden" name="status" value="{{ $filtro }}">
            <input type="text" name="busca" value="{{ request('busca') }}" placeholder="Buscar por nome ou CPF/CNPJ..."
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-72">
            <button type="submit" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 px-4 py-2 rounded-lg text-sm font-medium transition">
                Buscar
            </button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-blue-700 rounded-lg font-medium text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Nome</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">CPF/CNPJ</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">IE</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">UF</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($clientes as $cliente)
                    <tr class="hover:bg-gray-200 transition">
                        <td class="px-4 py-3 font-medium text-gray-800">
                            {{ $cliente->nome }}
                            @if ($cliente->tipo_pessoa === 'juridica' && $cliente->nome_fantasia)
                                <span class="text-xs text-gray-400 block font-normal">{{ $cliente->nome_fantasia }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $cliente->cpf_cnpj_formatado }}</td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ match($cliente->indicador_ie) {
                                'contribuinte' => 'Contribuinte',
                                'isento' => 'Isento',
                                default => 'Não Contribuinte',
                            } }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $cliente->uf ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($cliente->ativo)
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
                                <a href="{{ route('clientes.edit', $cliente) }}" class="text-blue-600 hover:text-blue-700 font-medium">Editar</a>
                                <form action="{{ route('clientes.toggleAtivo', $cliente) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="text-orange-600 hover:text-orange-700 font-medium">
                                        {{ $cliente->ativo ? 'Inativar' : 'Reativar' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhum cliente encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $clientes->links() }}
    </div>
@endsection