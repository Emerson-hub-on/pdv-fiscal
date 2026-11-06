@extends('layouts.app')
@section('titulo', 'Novo MDF-e')
@section('conteudo')
    <form action="{{ route('mdfes.store') }}" method="POST" class="w-full max-w-6xl mt-2 mb-6">
        @csrf
        @include('mdfes._form')
    </form>
@endsection
