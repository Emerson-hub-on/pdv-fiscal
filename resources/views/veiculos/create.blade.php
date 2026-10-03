@extends('layouts.app')
@section('titulo', 'Novo Veículo')
@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Novo Veículo</h1>
    <form action="{{ route('veiculos.store') }}" method="POST">
        @csrf
        @include('veiculos._form')
    </form>
@endsection