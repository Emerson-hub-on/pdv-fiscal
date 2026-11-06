@extends('layouts.app')
@section('titulo', 'Editar Veículo')
@section('conteudo')
@php extract(config('estilo.formulario')); @endphp
    <x-form.cabecalho solto rotulo="Cadastros · Veículos" titulo="Editar Veículo"
                      :voltar="route('veiculos.index')" voltar-titulo="Voltar para a lista" />
    <form action="{{ route('veiculos.update', $veiculo) }}" method="POST">
        @csrf
        @method('PUT')
        @include('veiculos._form')
    </form>
@endsection