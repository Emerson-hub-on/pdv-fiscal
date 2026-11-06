@extends('layouts.app')
@section('titulo', 'Editar Cliente')
@section('conteudo')
@php extract(config('estilo.formulario')); @endphp
    <x-form.cabecalho solto rotulo="Cadastros · Clientes" titulo="Editar Cliente"
                      :voltar="route('clientes.index')" voltar-titulo="Voltar para a lista" />
    <form action="{{ route('clientes.update', $cliente) }}" method="POST">
        @csrf
        @method('PUT')
        @include('clientes._form')
    </form>
@endsection
