@extends('layouts.app')

@section('titulo', 'Editar serviço')

@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Editar serviço</h1>
    <form action="{{ route('servicos.update', $servico) }}" method="POST">
        @csrf
        @method('PUT')
        @include('servicos._form')
    </form>
@endsection
