@extends('layouts.app')
@section('titulo', 'Editar Credenciadora')
@section('conteudo')
@php extract(config('estilo.formulario')); @endphp
    <x-form.cabecalho solto rotulo="Cadastros · Credenciadoras" titulo="Editar Credenciadora"
                      :voltar="route('credenciadoras.index')" voltar-titulo="Voltar para a lista" />
    <form action="{{ route('credenciadoras.update', $credenciadora) }}" method="POST">
        @csrf
        @method('PUT')
        @include('credenciadoras._form')
    </form>
@endsection
