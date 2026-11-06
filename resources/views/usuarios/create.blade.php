@extends('layouts.app')
@section('titulo', 'Novo ' . $cfg['singular'])

@section('breadcrumb')
    <span>Cadastros</span>
    <span class="text-gray-300">›</span>
    <span>Usuários</span>
    <span class="text-gray-300">›</span>
    <span class="text-gray-700 font-medium">{{ $cfg['singular'] }}</span>
@endsection

@section('conteudo')
@php extract(config('estilo.formulario')); @endphp
    <x-form.cabecalho solto rotulo="Cadastros · Usuários" titulo="Novo {{ $cfg['singular'] }}"
                      :voltar="route('usuarios.index', $perfil)" voltar-titulo="Voltar para a lista" />
    <form action="{{ route('usuarios.store', $perfil) }}" method="POST">
        @csrf
        @include('usuarios._form', ['usuario' => null])
    </form>

    <div class="mt-10 max-w-xl">
        <h2 class="text-lg font-semibold mb-1">Já tem cadastro em outro tipo?</h2>
        <p class="text-sm text-gray-500 mb-3">
            Informe o código da pessoa para adicionar o acesso de {{ $cfg['singular'] }} sem criar outro cadastro.
            O código e a senha continuam os mesmos.
        </p>

        <form action="{{ route('usuarios.vincular', $perfil) }}" method="POST"
              class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex gap-3 items-end">
            @csrf
            <div class="flex-1">
                <label class="{{ $labelCls }}">Código da pessoa</label>
                <input type="text" name="codigo" inputmode="numeric" pattern="[0-9]*" autocomplete="off"
                       class="{{ $inputCls }}">
            </div>
            <button type="submit" class="bg-gray-800 hover:bg-gray-700 text-white px-5 py-2.5 rounded-lg font-semibold text-sm transition">
                Adicionar como {{ $cfg['singular'] }}
            </button>
        </form>
    </div>
@endsection