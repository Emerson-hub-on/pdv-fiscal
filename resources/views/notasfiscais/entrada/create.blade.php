@extends('layouts.app')

@section('titulo', 'Nova Entrada de Nota')
@section('body-class', 'sidebar-oculta conteudo-largo')

@section('conteudo')
    @include('notasfiscais.entrada._form')
@endsection
