@extends('layouts.app')
@section('titulo', 'Editar Credenciadora')
@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Editar Credenciadora</h1>
    <form action="{{ route('credenciadoras.update', $credenciadora) }}" method="POST">
        @csrf
        @method('PUT')
        @include('credenciadoras._form')
    </form>
@endsection
