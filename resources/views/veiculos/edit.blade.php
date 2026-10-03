@extends('layouts.app')
@section('titulo', 'Editar Veículo')
@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Editar Veículo</h1>
    <form action="{{ route('veiculos.update', $veiculo) }}" method="POST">
        @csrf
        @method('PUT')
        @include('veiculos._form')
    </form>
@endsection