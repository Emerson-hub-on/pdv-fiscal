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

    {{-- Fora do formulário principal (HTML não aceita form dentro de form); o botão Excluir aponta para ele --}}
    <form id="form-excluir-forma" method="POST" action="{{ route('formas-pagamento-caixa.destroy', $forma) }}"
          onsubmit="return confirm('Excluir a forma de pagamento &quot;{{ e($forma->descricao) }}&quot;?')" class="hidden">
        @csrf
        @method('DELETE')
    </form>
@endsection
