@extends('layouts.app')

@section('titulo', 'Editar Produto')

@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Editar Produto</h1>
    <form action="{{ route('produtos.update', $produto) }}" method="POST">
        @csrf
        @method('PUT')
        @include('produtos._form')
    </form>
    @include('produtos._modais_catalogo')
@endsection