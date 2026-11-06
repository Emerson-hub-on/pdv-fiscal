@extends('layouts.app')
@section('titulo', 'Editar Condutor')
@section('conteudo')
@php extract(config('estilo.formulario')); @endphp
    <x-form.cabecalho solto rotulo="Cadastros · Condutores" titulo="Editar Condutor"
                      :voltar="route('condutores.index')" voltar-titulo="Voltar para a lista" />
    <form action="{{ route('condutores.update', $condutor) }}" method="POST">
        @csrf
        @method('PUT')
        @include('condutores._form')
    </form>
@endsection
