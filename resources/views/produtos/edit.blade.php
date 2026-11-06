@extends('layouts.app')
@section('titulo', 'Editar Produto')
@section('conteudo')
@php extract(config('estilo.formulario')); @endphp
    <x-form.cabecalho solto rotulo="Cadastros · Produtos" titulo="Editar Produto"
                      :voltar="route('produtos.index')" voltar-titulo="Voltar para a lista" />
    <form id="form-produto" action="{{ route('produtos.update', $produto) }}" method="POST">
        @csrf
        @method('PUT')
        @include('produtos._form')
        @include('produtos._modais_catalogo')
    </form>
@endsection