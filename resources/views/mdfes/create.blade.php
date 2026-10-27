@extends('layouts.app')
@section('titulo', 'Novo MDF-e')
@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Novo MDF-e</h1>
    <form action="{{ route('mdfes.store') }}" method="POST">
        @csrf
        @include('mdfes._form')
    </form>
@endsection
