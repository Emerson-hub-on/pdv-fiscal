@extends('layouts.app')
@section('titulo', 'Permissões - ' . $usuario->name)

@section('breadcrumb')
    <span>Cadastros</span>
    <span class="text-gray-300">›</span>
    <span>Usuários</span>
    <span class="text-gray-300">›</span>
    <span>{{ $cfg['singular'] }}</span>
    <span class="text-gray-300">›</span>
    <span class="text-gray-700 font-medium">Permissões</span>
@endsection

@section('conteudo')
    <h1 class="text-2xl font-bold mb-1">Permissões de {{ $usuario->name }}</h1>
    <p class="text-sm text-gray-500 mb-6">
        Por padrão, estas ações exigem a autorização de um supervisor no caixa.
        Marque "Liberado" para que este operador execute a ação sem pedir autorização.
    </p>

    <form action="{{ route('usuarios.permissoes.salvar', [$perfil, $usuario]) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden max-w-4xl">
            <table class="w-full text-sm">
                <thead class="bg-gray-700 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Ação</th>
                        @foreach ($niveis as $nome)
                            <th class="px-4 py-3 text-center font-medium text-amber-50">{{ $nome }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($acoes as $chave => $acao)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800">{{ $acao['nome'] }}</p>
                                <p class="text-xs text-gray-400">{{ $acao['descricao'] }}</p>
                            </td>
                            @foreach ($niveis as $valor => $nome)
                                <td class="px-4 py-3 text-center">
                                    <input type="radio" name="permissoes[{{ $chave }}]" value="{{ $valor }}"
                                           {{ old("permissoes.$chave", $atuais[$chave]) === $valor ? 'checked' : '' }}>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">
                Salvar permissões
            </button>
            <button type="button" onclick="document.querySelectorAll('input[value=supervisor]').forEach(r => r.checked = true)"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg text-sm font-medium transition">
                Exigir supervisor em tudo
            </button>
            <a href="{{ route('usuarios.index', $perfil) }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg text-sm font-medium transition">
                Cancelar
            </a>
        </div>
    </form>
@endsection