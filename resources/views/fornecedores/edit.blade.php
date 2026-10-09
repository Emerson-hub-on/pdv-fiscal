@extends('layouts.app')
@section('titulo', 'Editar Fornecedor')
@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Editar Fornecedor</h1>
    <form action="{{ route('fornecedores.update', $fornecedor) }}" method="POST">
        @csrf
        @method('PUT')
        @include('fornecedores._form')
    </form>
@endsection
