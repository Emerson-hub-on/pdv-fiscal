@extends('layouts.app')
@section('titulo', 'Novo Veículo')
@section('conteudo')
@php extract(config('estilo.formulario')); @endphp
    <x-form.cabecalho solto rotulo="Cadastros · Veículos" titulo="Novo Veículo"
                      :voltar="route('veiculos.index')" voltar-titulo="Voltar para a lista" />
    <form action="{{ route('veiculos.store') }}" method="POST">
        @csrf
        @include('veiculos._form')
    </form>
@endsection