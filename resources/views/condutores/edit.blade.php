@extends('layouts.app')
@section('titulo', 'Editar Condutor')
@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Editar Condutor</h1>
    <form action="{{ route('condutores.update', $condutor) }}" method="POST">
        @csrf
        @method('PUT')
        @include('condutores._form')
    </form>
@endsection
