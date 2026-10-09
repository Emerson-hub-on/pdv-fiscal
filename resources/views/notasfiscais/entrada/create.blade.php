@extends('layouts.app')

@section('titulo', 'Nova Entrada de Nota')
@section('body-class', 'sidebar-oculta conteudo-largo')

@section('conteudo')
    @if (session('erro_xml'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {{ session('erro_xml') }}
        </div>
    @endif

    @error('xml')
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {{ $message }}
        </div>
    @enderror

    <div class="flex justify-end mb-4">
        <button type="button" onclick="abrirModalXml()"
                class="bg-gray-700 hover:bg-gray-500 text-amber-50 px-4 py-2 rounded-lg text-sm font-medium transition">
            Importar XML
        </button>
    </div>

    @include('notasfiscais.entrada._form')
    @include('notasfiscais.entrada._modal-importar-xml')
@endsection