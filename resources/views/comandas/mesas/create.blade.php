@extends('layouts.app')
@section('titulo', 'Nova mesa')
@section('conteudo')
    <x-form.cabecalho solto rotulo="Comandas · Mesas · Lista de Mesas" titulo="Nova mesa"
                      :voltar="route('mesas.index')" voltar-titulo="Voltar para a lista" />
    <form action="{{ route('mesas.store') }}" method="POST">
        @csrf
        @include('comandas.mesas._form')
    </form>
@endsection
