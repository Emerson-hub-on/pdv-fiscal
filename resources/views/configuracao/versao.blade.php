@extends('layouts.app')

@section('titulo', 'Versão')

@php
    $historico = config('versao.historico', []);
@endphp

@section('conteudo')
<div class="max-w-2xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex items-center gap-5">
        @include('partials.logo-simbolo', ['tamanho' => 56, 'fundo' => 'claro'])
        <div>
            <p class="text-sm text-gray-500">Cyberly Sistema</p>
            <p class="text-3xl font-bold text-gray-800 tracking-tight">V.{{ config('versao.atual') }}</p>
            @if (!empty($historico[0]['data']))
                <p class="text-xs text-gray-500 mt-1">Atualizado em {{ \Carbon\Carbon::parse($historico[0]['data'])->format('d/m/Y') }}</p>
            @endif
        </div>
    </div>

    @if (!empty($historico[0]['itens']))
        <h2 class="text-sm font-semibold text-gray-700 mt-8 mb-3">O que mudou nesta versão</h2>
        <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
            <ul class="list-disc pl-5 space-y-0.5 text-sm text-gray-600">
                @foreach ($historico[0]['itens'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
