@extends('layouts.app')
@section('titulo', 'Novo PDV')
@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Novo PDV</h1>
    <form action="{{ route('pdvs.store') }}" method="POST" class="bg-white p-6 rounded shadow">
        @csrf
        @include('pdvs._form')
    </form>
@endsection