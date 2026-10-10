@extends('layouts.app')

@section('titulo', 'Editar Entrada de Nota')
@section('body-class', 'sidebar-oculta conteudo-largo')

@section('conteudo')
    @if (session('avisos'))
        <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach (session('avisos') as $aviso)
                    <li>{{ $aviso }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @include('notasfiscais.entrada._form')
    @include('notasfiscais.entrada._modal-operacao')
@endsection