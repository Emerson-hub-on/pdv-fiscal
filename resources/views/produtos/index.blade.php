@extends('layouts.app')

@section('titulo', 'Produtos')

@section('conteudo')
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Produtos</h1>
        <a href="{{ route('produtos.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            + Novo Produto
        </a>
    </div>

    <div class="flex gap-2 mb-4">
        <a href="{{ route('produtos.index', ['status' => 'ativos']) }}"
           class="px-3 py-1 rounded {{ $filtro === 'ativos' ? 'bg-blue-600 text-white' : 'bg-gray-200' }}">Ativos</a>
        <a href="{{ route('produtos.index', ['status' => 'inativos']) }}"
           class="px-3 py-1 rounded {{ $filtro === 'inativos' ? 'bg-blue-600 text-white' : 'bg-gray-200' }}">Inativos</a>
        <a href="{{ route('produtos.index', ['status' => 'todos']) }}"
           class="px-3 py-1 rounded {{ $filtro === 'todos' ? 'bg-blue-600 text-white' : 'bg-gray-200' }}">Todos</a>
    </div>

    <table class="w-full bg-white rounded shadow overflow-hidden">
        <thead class="bg-gray-800 text-white text-left">
            <tr>
                <th class="p-3">Nome</th>
                <th class="p-3">Categoria</th>
                <th class="p-3">Preço</th>
                <th class="p-3">Estoque</th>
                <th class="p-3">Status</th>
                <th class="p-3">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($produtos as $produto)
                <tr class="border-b">
                    <td class="p-3">{{ $produto->nome }}</td>
                    <td class="p-3">{{ $produto->categoria ?? '-' }}</td>
                    <td class="p-3">R$ {{ number_format($produto->preco_venda, 2, ',', '.') }}</td>
                    <td class="p-3">{{ $produto->estoque_total }}</td>
                    <td class="p-3">
                        <span class="{{ $produto->ativo ? 'text-green-600' : 'text-red-500' }}">
                            {{ $produto->ativo ? 'Ativo' : 'Inativo' }}
                        </span>
                    </td>
                    <td class="p-3 flex gap-2">
                        <a href="{{ route('produtos.edit', $produto) }}" class="text-blue-600 hover:underline">Editar</a>
                        <form action="{{ route('produtos.toggle-ativo', $produto) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="text-orange-600 hover:underline">
                                {{ $produto->ativo ? 'Inativar' : 'Reativar' }}
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="p-6 text-center text-gray-400">Nenhum produto encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">
        {{ $produtos->links() }}
    </div>
@endsection