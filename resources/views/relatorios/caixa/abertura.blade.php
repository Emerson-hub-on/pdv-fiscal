@extends('layouts.app')

@section('titulo', 'Relatório de Abertura de Caixa')

@php
    $moeda = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
@endphp

@section('conteudo')
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Abertura de Caixa</h1>

    @if ($errors->any())
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    @include('relatorios.caixa._filtros', [
        'rota' => 'relatorios.caixa.abertura',
        'rotuloData' => 'Aberto de',
        'comStatus' => true,
    ])

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Aberturas no período</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $totais['quantidade'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Fundo de troco entregue</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $moeda($totais['fundo']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Ainda abertos</p>
            <p class="text-2xl font-bold {{ $totais['abertos'] ? 'text-amber-600' : 'text-gray-800' }} mt-1">{{ $totais['abertos'] }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wide">
                <tr>
                    <th class="text-left px-4 py-3">Caixa nº</th>
                    <th class="text-left px-4 py-3">Abertura</th>
                    <th class="text-left px-4 py-3">PDV</th>
                    <th class="text-left px-4 py-3">Operador</th>
                    <th class="text-right px-4 py-3">Fundo de troco</th>
                    <th class="text-left px-4 py-3">Situação</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($caixas as $caixa)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ str_pad($caixa->id, 6, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-4 py-3">{{ $caixa->data_abertura?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $caixa->pdv->nome ?? '-' }}</td>
                        <td class="px-4 py-3">
                            {{ $caixa->operador?->codigo ? str_pad($caixa->operador->codigo, 4, '0', STR_PAD_LEFT) . ' - ' : '' }}{{ $caixa->operador->name ?? 'Operador #' . $caixa->operador_id }}
                        </td>
                        <td class="px-4 py-3 text-right">{{ $moeda($caixa->valor_abertura) }}</td>
                        <td class="px-4 py-3">
                            @if ($caixa->status === 'aberto')
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Aberto</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
                                    Fechado {{ $caixa->data_fechamento?->format('d/m H:i') }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('relatorios.caixa.abertura.imprimir', ['caixa' => $caixa, 'auto' => 1]) }}" target="_blank"
                               class="inline-block px-3 py-1.5 text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
                                Imprimir 2ª via
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-gray-400">Nenhuma abertura de caixa no período.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $caixas->links() }}</div>
@endsection
