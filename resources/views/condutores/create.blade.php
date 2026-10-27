@extends('layouts.app')
@section('titulo', 'Novo Condutor')
@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Novo Condutor</h1>
    <form action="{{ route('condutores.store') }}" method="POST">
        @csrf
        @include('condutores._form')
    </form>
@endsection
