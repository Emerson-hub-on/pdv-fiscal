@extends('layouts.app')
@section('titulo', 'Nova Credenciadora')
@section('conteudo')
@php extract(config('estilo.formulario')); @endphp
    <x-form.cabecalho solto rotulo="Cadastros · Credenciadoras" titulo="Nova Credenciadora"
                      :voltar="route('credenciadoras.index')" voltar-titulo="Voltar para a lista" />
    <form action="{{ route('credenciadoras.store') }}" method="POST">
        @csrf
        @include('credenciadoras._form')
    </form>
@endsection
