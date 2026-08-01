@extends('layouts.app')
@section('titulo', 'PDVs')
@section('conteudo')
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">PDVs</h1>
        <a href="{{ route('pdvs.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            + Novo PDV
        </a>
    </div>

    <table class="w-full bg-white rounded shadow overflow-hidden">
        <thead class="bg-gray-800 text-white text-left">
            <tr>
                <th class="p-3">Nome</th>
                <th class="p-3">Série</th>
                <th class="p-3">Número atual</th>
                <th class="p-3">Status</th>
                <th class="p-3">Ações</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pdvs as $pdv)
                <tr class="border-b">
                    <td class="p-3">{{ $pdv->nome }}</td>
                    <td class="p-3">{{ $pdv->serie_nfce }}</td>
                    <td class="p-3">{{ $pdv->numero_atual_nfce }}</td>
                    <td class="p-3">
                        <span class="{{ $pdv->ativo ? 'text-green-600' : 'text-red-500' }}">
                            {{ $pdv->ativo ? 'Ativo' : 'Inativo' }}
                        </span>
                    </td>
                    <td class="p-3 flex gap-2">
                        <a href="{{ route('pdvs.edit', $pdv) }}" class="text-blue-600 hover:underline">Editar</a>
                        <form action="{{ route('pdvs.toggle-ativo', $pdv) }}" method="POST">
                            @csrf @method('PATCH')
                            <button class="text-orange-600 hover:underline">
                                {{ $pdv->ativo ? 'Inativar' : 'Reativar' }}
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection