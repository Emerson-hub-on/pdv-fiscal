@extends('layouts.app')

@section('titulo', 'Nova Nota Fiscal')

@section('conteudo')
    @include('notasfiscais._form', ['notaFiscal' => null, 'clientes' => $clientes])
@endsection