@extends('layouts.app')
@section('titulo', 'Nova Transportadora')
@section('conteudo')
@php extract(config('estilo.formulario')); @endphp
    <x-form.cabecalho solto rotulo="Cadastros · Transportadoras" titulo="Nova Transportadora"
                      :voltar="route('transportadores.index')" voltar-titulo="Voltar para a lista" />
    <form action="{{ route('transportadores.store') }}" method="POST">
        @csrf
        @include('transportadores._form')
    </form>
@endsection