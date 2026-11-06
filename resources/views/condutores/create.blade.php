@extends('layouts.app')
@section('titulo', 'Novo Condutor')
@section('conteudo')
@php extract(config('estilo.formulario')); @endphp
    <x-form.cabecalho solto rotulo="Cadastros · Condutores" titulo="Novo Condutor"
                      :voltar="route('condutores.index')" voltar-titulo="Voltar para a lista" />
    <form action="{{ route('condutores.store') }}" method="POST">
        @csrf
        @include('condutores._form')
    </form>
@endsection
