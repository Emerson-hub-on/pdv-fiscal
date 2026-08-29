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

    <div class="flex gap-2 mb-4">
        <a href="{{ route('produtos.index', ['status' => 'ativos']) }}"
           class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $filtro === 'ativos' ? 'bg-gray-600 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50' }}">
            Ativos
        </a>
        <a href="{{ route('produtos.index', ['status' => 'inativos']) }}"
           class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $filtro === 'inativos' ? 'bg-gray-600 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50' }}">
            Inativos
        </a>
        <a href="{{ route('produtos.index', ['status' => 'todos']) }}"
           class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $filtro === 'todos' ? 'bg-gray-600 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50' }}">
            Todos
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-600 rounded-lg font-medium  text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Nome</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Categoria</th>
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
                        <td class="px-4 py-3 text-gray-600">{{ $produto->categoria->nome ?? '-' }}</td>
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
                        <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhum produto encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $produtos->links() }}
    </div>
@endsection