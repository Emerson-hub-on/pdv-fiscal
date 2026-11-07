@extends('layouts.app')
@section('titulo', 'Adicionar ao cardápio')
@section('conteudo')
    <x-form.cabecalho solto rotulo="Comandas · Cardápio · Menu" titulo="Adicionar produto ao cardápio"
                      :voltar="route('cardapio.index')" voltar-titulo="Voltar para o cardápio" />
    <form action="{{ route('cardapio.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @include('comandas.cardapio._form')
    </form>
@endsection
