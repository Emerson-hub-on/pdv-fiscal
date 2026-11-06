@extends('layouts.app')
@section('titulo', 'Novo Cliente')
@section('conteudo')
@php extract(config('estilo.formulario')); @endphp
    <x-form.cabecalho solto rotulo="Cadastros · Clientes" titulo="Novo Cliente"
                      :voltar="route('clientes.index')" voltar-titulo="Voltar para a lista" />
    <form action="{{ route('clientes.store') }}" method="POST">
        @csrf
        @include('clientes._form')


    </form>
@endsection
