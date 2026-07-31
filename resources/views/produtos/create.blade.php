@extends('layouts.app')

@section('titulo', 'Novo Produto')

@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Novo Produto</h1>

    <form action="{{ route('produtos.store') }}" method="POST" class="bg-white p-6 rounded shadow">
        @csrf
        @include('produtos._form')
    </form>
@endsection