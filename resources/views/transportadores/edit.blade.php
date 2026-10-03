@extends('layouts.app')
@section('titulo', 'Editar Transportadora')
@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Editar Transportadora</h1>
    <form action="{{ route('transportadores.update', $transportador) }}" method="POST">
        @csrf
        @method('PUT')
        @include('transportadores._form')
    </form>
@endsection