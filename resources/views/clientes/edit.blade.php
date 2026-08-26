@extends('layouts.app')
@section('titulo', 'Editar Cliente')
@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Editar Cliente</h1>
    <form action="{{ route('clientes.update', $cliente) }}" method="POST">
        @csrf
        @method('PUT')
        @include('clientes._form')
    </form>
@endsection
