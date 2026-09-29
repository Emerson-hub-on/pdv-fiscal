@extends('layouts.app')

@section('titulo', 'Nova Nota Fiscal')
@section('body-class', 'sidebar-oculta conteudo-largo')

@section('conteudo')
    @include('notasfiscais._form', ['notaFiscal' => null, 'clientes' => $clientes])
@endsection