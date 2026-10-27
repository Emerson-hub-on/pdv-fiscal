@extends('layouts.app')
@section('titulo', 'Editar MDF-e')
@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Editar MDF-e</h1>
    <form action="{{ route('mdfes.update', $mdfe) }}" method="POST">
        @csrf
        @method('PUT')
        @include('mdfes._form')
    </form>
@endsection
