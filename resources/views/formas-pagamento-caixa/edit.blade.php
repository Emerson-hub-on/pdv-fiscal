@extends('layouts.app')
@section('titulo', 'Editar forma de pagamento')
@section('conteudo')
    <x-form.cabecalho solto rotulo="Cadastros · Caixa · Formas de pagamento" :titulo="'Editar: ' . $forma->descricao"
                      :voltar="route('formas-pagamento-caixa.index')" voltar-titulo="Voltar para a lista"
                      :selo="$forma->sistema ? 'Do sistema' : null" />
    <form action="{{ route('formas-pagamento-caixa.update', $forma) }}" method="POST">
        @csrf
        @method('PUT')
        @include('formas-pagamento-caixa._form')
    </form>
@endsection
