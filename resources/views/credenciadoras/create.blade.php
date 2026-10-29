@extends('layouts.app')
@section('titulo', 'Nova Credenciadora')
@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Nova Credenciadora</h1>
    <form action="{{ route('credenciadoras.store') }}" method="POST">
        @csrf
        @include('credenciadoras._form')
    </form>
@endsection
