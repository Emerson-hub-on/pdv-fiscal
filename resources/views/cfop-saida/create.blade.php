@extends('layouts.app')
@section('titulo', 'Novo CFOP')
@section('conteudo')
    @include('cfop-saida._form', ['cfopSaida' => null])
@endsection