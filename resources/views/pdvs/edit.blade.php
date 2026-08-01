@extends('layouts.app')
@section('titulo', 'Editar PDV')
@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Editar PDV</h1>
    <form action="{{ route('pdvs.update', $pdv) }}" method="POST" class="bg-white p-6 rounded shadow">
        @csrf
        @method('PUT')
        @include('pdvs._form')
    </form>
@endsection