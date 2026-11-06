@extends('layouts.app')
@section('titulo', 'Novo Produto')
@section('conteudo')
@php extract(config('estilo.formulario')); @endphp
    <x-form.cabecalho solto rotulo="Cadastros · Produtos" titulo="Novo Produto"
                      :voltar="route('produtos.index')" voltar-titulo="Voltar para a lista" />
    <form id="form-produto" action="{{ route('produtos.store') }}" method="POST" class="bg-white p-6 rounded shadow">
        @csrf
        @include('produtos._form', ['proximoCodigo' => $proximoCodigo])
        @include('produtos._modais_catalogo')
    </form>
@endsection