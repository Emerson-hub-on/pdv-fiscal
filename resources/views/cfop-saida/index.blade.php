@extends('layouts.app')
@section('titulo', 'CFOP de Saída')
@section('conteudo')
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="flex justify-between items-center p-4 border-b border-gray-100">
        <h1 class="text-lg font-semibold">CFOP de Saída</h1>
        <a href="{{ route('cfop-saida.create') }}" class="bg-gray-800 text-white rounded-lg px-4 py-2 text-sm hover:bg-gray-700">Novo CFOP</a>
    </div>

    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
                <th class="text-left px-4 py-2">Código</th>
                <th class="text-left px-4 py-2">Descrição</th>
                <th class="text-left px-4 py-2">Finalidade</th>
                <th class="text-center px-4 py-2">Movimenta estoque</th>
                <th class="text-center px-4 py-2">Ativo</th>
                <th class="text-right px-4 py-2">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach ($cfops as $cfop)
                <tr>
                    <td class="px-4 py-2 font-mono">{{ $cfop->codigo }}</td>
                    <td class="px-4 py-2">{{ $cfop->descricao }}</td>
                    <td class="px-4 py-2">{{ $cfop->finalidade ?? '—' }}</td>
                    <td class="px-4 py-2 text-center">{{ $cfop->movimenta_estoque ? 'Sim' : 'Não' }}</td>
                    <td class="px-4 py-2 text-center">{{ $cfop->ativo ? 'Sim' : 'Não' }}</td>
                    <td class="px-4 py-2 text-right">
                        <a href="{{ route('cfop-saida.edit', $cfop) }}" class="text-blue-600 hover:underline text-xs mr-3">Editar</a>
                        <form method="POST" action="{{ route('cfop-saida.toggle-ativo', $cfop) }}" class="inline">
                            @csrf @method('PATCH')
                            <button class="text-xs {{ $cfop->ativo ? 'text-red-600' : 'text-green-600' }} hover:underline">
                                {{ $cfop->ativo ? 'Inativar' : 'Reativar' }}
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="p-4">{{ $cfops->links() }}</div>
</div>
@endsection