@extends('layouts.app')
@section('titulo', 'Novo PDV')
@section('conteudo')
@php extract(config('estilo.formulario')); @endphp
    <x-form.cabecalho solto rotulo="Configuração · PDVs" titulo="Novo PDV"
                      :voltar="route('pdvs.index')" voltar-titulo="Voltar para a lista" />
    <form action="{{ route('pdvs.store') }}" method="POST" class="bg-white p-6 rounded shadow">
        @csrf
        @include('pdvs._form')
    </form>
@endsection