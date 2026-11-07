@extends('layouts.app')
@section('titulo', 'Editar mesa')
@section('conteudo')
    <x-form.cabecalho solto rotulo="Comandas · Mesas · Lista de Mesas" :titulo="'Editar: ' . $mesa->titulo()"
                      :voltar="route('mesas.index')" voltar-titulo="Voltar para a lista"
                      :selo="$mesa->ativo ? null : 'Inativa'" />
    <form action="{{ route('mesas.update', $mesa) }}" method="POST">
        @csrf
        @method('PUT')
        @include('comandas.mesas._form')
    </form>

    {{-- Fora do formulário principal (HTML não aceita form dentro de form); o botão Excluir aponta para ele --}}
    <form id="form-excluir-mesa" method="POST" action="{{ route('mesas.destroy', $mesa) }}"
          onsubmit="return confirm({{ Illuminate\Support\Js::from('Excluir a ' . $mesa->titulo() . '?') }})" class="hidden">
        @csrf
        @method('DELETE')
    </form>
@endsection
