@extends('layouts.app')

@section('titulo', 'Editar Nota Fiscal')

@section('conteudo')
    @include('notasfiscais._form', ['notaFiscal' => $notaFiscal, 'clientes' => $clientes])
@endsection