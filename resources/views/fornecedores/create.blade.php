@extends('layouts.app')
@section('titulo', 'Novo Fornecedor')
@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Novo Fornecedor</h1>
    <form action="{{ route('fornecedores.store') }}" method="POST">
        @csrf
        @include('fornecedores._form')
    </form>
@endsection
