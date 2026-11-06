@extends('layouts.app')
@section('titulo', 'Editar ' . $cfg['singular'])

@section('breadcrumb')
    <span>Cadastros</span>
    <span class="text-gray-300">›</span>
    <span>Usuários</span>
    <span class="text-gray-300">›</span>
    <span class="text-gray-700 font-medium">{{ $cfg['singular'] }}</span>
@endsection

@section('conteudo')
@php extract(config('estilo.formulario')); @endphp
    <x-form.cabecalho solto rotulo="Cadastros · Usuários" titulo="Editar {{ $cfg['singular'] }}"
                      :voltar="route('usuarios.index', $perfil)" voltar-titulo="Voltar para a lista" />
    <form action="{{ route('usuarios.update', [$perfil, $usuario]) }}" method="POST">
        @csrf
        @method('PUT')
        @include('usuarios._form')
    </form>
@endsection