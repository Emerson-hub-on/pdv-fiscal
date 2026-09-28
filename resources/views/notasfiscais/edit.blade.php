@extends('layouts.app')

@section('titulo', 'Editar Nota Fiscal')
@section('body-class', 'sidebar-oculta')

@section('conteudo')
    @include('notasfiscais._form', ['notaFiscal' => $notaFiscal, 'clientes' => $clientes])
@endsection