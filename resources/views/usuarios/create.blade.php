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
    <h1 class="text-2xl font-bold mb-6">Novo {{ $cfg['singular'] }}</h1>
    <form action="{{ route('usuarios.store', $perfil) }}" method="POST">
        @csrf
        @include('usuarios._form', ['usuario' => null])
    </form>
@endsection