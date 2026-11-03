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

    <h2 class="text-sm font-semibold text-gray-700 mt-8 mb-3">Histórico de versões</h2>
    <ol class="space-y-3">
        @foreach ($historico as $entrada)
            <li class="bg-white rounded-xl border border-gray-200 px-5 py-4">
                <div class="flex items-baseline justify-between gap-3">
                    <span class="font-semibold text-gray-800">V.{{ $entrada['versao'] }}</span>
                    <span class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($entrada['data'])->format('d/m/Y') }}</span>
                </div>
                <ul class="mt-2 list-disc pl-5 space-y-0.5 text-sm text-gray-600">
                    @foreach ($entrada['itens'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </li>
        @endforeach
    </ol>
</div>
@endsection
