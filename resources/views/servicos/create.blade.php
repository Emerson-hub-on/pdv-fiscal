@extends('layouts.app')

@section('titulo', 'Novo serviço')

@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Novo serviço</h1>
    <form action="{{ route('servicos.store') }}" method="POST">
        @csrf
        @include('servicos._form')
    </form>
@endsection
