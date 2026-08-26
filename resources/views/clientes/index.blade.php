@extends('layouts.app')
@section('titulo', 'Clientes')
@section('conteudo')
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Clientes</h1>
        <a href="{{ route('clientes.create') }}"
           class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            + Novo Cliente
        </a>
    </div>

    <form method="GET" class="flex gap-2 mb-4">
        <input type="text" name="busca" value="{{ request('busca') }}" placeholder="Buscar por nome ou CPF/CNPJ..."
               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white" onchange="this.form.submit()">
            <option value="ativos" {{ $filtro === 'ativos' ? 'selected' : '' }}>Ativos</option>
            <option value="inativos" {{ $filtro === 'inativos' ? 'selected' : '' }}>Inativos</option>
            <option value="todos" {{ $filtro === 'todos' ? 'selected' : '' }}>Todos</option>
        </select>
        <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">
            Buscar
        </button>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left">Nome</th>
                    <th class="px-4 py-3 text-left">CPF/CNPJ</th>
                    <th class="px-4 py-3 text-left">IE</th>
                    <th class="px-4 py-3 text-left">UF</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($clientes as $cliente)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            {{ $cliente->nome }}
                            @if ($cliente->tipo_pessoa === 'juridica' && $cliente->nome_fantasia)
                                <span class="text-xs text-gray-400 block">{{ $cliente->nome_fantasia }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $cliente->cpf_cnpj_formatado }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            {{ match($cliente->indicador_ie) {
                                'contribuinte' => 'Contribuinte',
                                'isento' => 'Isento',
                                default => 'Não Contribuinte',
                            } }}
                        </td>
                        <td class="px-4 py-3">{{ $cliente->uf ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('clientes.edit', $cliente) }}" class="text-blue-600 hover:underline text-xs mr-3">Editar</a>
                            <form action="{{ route('clientes.toggleAtivo', $cliente) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-xs {{ $cliente->ativo ? 'text-red-500' : 'text-green-600' }} hover:underline">
                                    {{ $cliente->ativo ? 'Inativar' : 'Reativar' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">Nenhum cliente encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $clientes->links() }}
    </div>
@endsection
