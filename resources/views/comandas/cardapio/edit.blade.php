@extends('layouts.app')
@section('titulo', 'Editar item do cardápio')
@section('conteudo')
    <x-form.cabecalho solto rotulo="Comandas · Cardápio · Menu" :titulo="'Editar: ' . $item->nomeExibido()"
                      :voltar="route('cardapio.index')" voltar-titulo="Voltar para o cardápio"
                      :selo="$item->ativo ? null : 'Inativo'" />
    <form action="{{ route('cardapio.update', $item) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('comandas.cardapio._form')
    </form>

    {{-- Fora do formulário principal (HTML não aceita form dentro de form); o botão "Retirar do cardápio" aponta para ele --}}
    <form id="form-excluir-item" method="POST" action="{{ route('cardapio.destroy', $item) }}"
          onsubmit="return confirm('Retirar &quot;{{ e($item->nomeExibido()) }}&quot; do cardápio? O cadastro do produto não é alterado.')" class="hidden">
        @csrf
        @method('DELETE')
    </form>
@endsection
