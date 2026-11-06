@extends('layouts.app')
@section('titulo', 'Nova forma de pagamento')
@section('conteudo')
    <x-form.cabecalho solto rotulo="Cadastros · Caixa · Formas de pagamento" titulo="Nova forma de pagamento"
                      :voltar="route('formas-pagamento-caixa.index')" voltar-titulo="Voltar para a lista" />
    <form action="{{ route('formas-pagamento-caixa.store') }}" method="POST">
        @csrf
        @include('formas-pagamento-caixa._form')
    </form>
@endsection
