@extends('layouts.app')
@section('titulo', 'Nova Transportadora')
@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Nova Transportadora</h1>
    <form action="{{ route('transportadores.store') }}" method="POST">
        @csrf
        @include('transportadores._form')
    </form>
@endsection