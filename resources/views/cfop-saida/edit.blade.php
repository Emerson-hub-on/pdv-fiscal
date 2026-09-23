@extends('layouts.app')
@section('titulo', 'Editar CFOP')
@section('conteudo')
    @include('cfop-saida._form', ['cfopSaida' => $cfopSaida])
@endsection