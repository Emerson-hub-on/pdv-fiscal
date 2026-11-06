@extends('layouts.app')
@section('titulo', 'Editar PDV')
@section('conteudo')
@php extract(config('estilo.formulario')); @endphp
    <x-form.cabecalho solto rotulo="Configuração · PDVs" titulo="Editar PDV"
                      :voltar="route('pdvs.index')" voltar-titulo="Voltar para a lista" />
    <form action="{{ route('pdvs.update', $pdv) }}" method="POST" class="bg-white p-6 rounded shadow">
        @csrf
        @method('PUT')
        @include('pdvs._form')
    </form>
@endsection