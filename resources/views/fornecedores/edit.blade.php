@extends('layouts.app')
@section('titulo', 'Editar Fornecedor')
@section('conteudo')
@php extract(config('estilo.formulario')); @endphp
    <x-form.cabecalho solto rotulo="Cadastros · Fornecedores" titulo="Editar Fornecedor"
                      :voltar="route('fornecedores.index')" voltar-titulo="Voltar para a lista" />
    <form action="{{ route('fornecedores.update', $fornecedor) }}" method="POST">
        @csrf
        @method('PUT')
        @include('fornecedores._form')
    </form>
@endsection
