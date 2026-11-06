@extends('layouts.app')
@section('titulo', 'Editar Transportadora')
@section('conteudo')
@php extract(config('estilo.formulario')); @endphp
    <x-form.cabecalho solto rotulo="Cadastros · Transportadoras" titulo="Editar Transportadora"
                      :voltar="route('transportadores.index')" voltar-titulo="Voltar para a lista" />
    <form action="{{ route('transportadores.update', $transportador) }}" method="POST">
        @csrf
        @method('PUT')
        @include('transportadores._form')
    </form>
@endsection