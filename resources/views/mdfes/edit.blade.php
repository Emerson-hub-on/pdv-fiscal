@extends('layouts.app')
@section('titulo', 'Editar MDF-e')
@section('conteudo')
    <form action="{{ route('mdfes.update', $mdfe) }}" method="POST" class="w-full max-w-6xl mt-2 mb-6">
        @csrf
        @method('PUT')
        @include('mdfes._form')
    </form>
@endsection
