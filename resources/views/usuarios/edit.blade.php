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
    <h1 class="text-2xl font-bold mb-6">Editar {{ $cfg['singular'] }}</h1>
    <form action="{{ route('usuarios.update', [$perfil, $usuario]) }}" method="POST">
        @csrf
        @method('PUT')
        @include('usuarios._form')
    </form>
@endsection