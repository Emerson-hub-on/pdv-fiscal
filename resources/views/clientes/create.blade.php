@extends('layouts.app')
@section('titulo', 'Novo Cliente')
@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Novo Cliente</h1>
    <form action="{{ route('clientes.store') }}" method="POST">
        @csrf
        @include('clientes._form')
    </form>
@endsection
