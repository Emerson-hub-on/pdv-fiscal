@extends('layouts.app')
@section('titulo', 'Novo Fornecedor')
@section('conteudo')
@php extract(config('estilo.formulario')); @endphp
    <x-form.cabecalho solto rotulo="Cadastros · Fornecedores" titulo="Novo Fornecedor"
                      :voltar="route('fornecedores.index')" voltar-titulo="Voltar para a lista" />
    <form action="{{ route('fornecedores.store') }}" method="POST">
        @csrf
        @include('fornecedores._form')
    </form>
@endsection
