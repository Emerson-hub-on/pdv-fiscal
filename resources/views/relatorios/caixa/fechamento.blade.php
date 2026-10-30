@extends('layouts.app')

@section('titulo', 'Relatório de Fechamento de Caixa')

@php
    $moeda = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $corDiferenca = fn ($v) => abs($v) < 0.01 ? 'text-green-700' : ($v > 0 ? 'text-blue-700' : 'text-red-600');
@endphp

@section('conteudo')
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Fechamento de Caixa</h1>

    @if ($errors->any())
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    @include('relatorios.caixa._filtros', [
        'rota' => 'relatorios.caixa.fechamento',
        'rotuloData' => 'Fechado de',
    ])

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Caixas fechados</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $totais['quantidade'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Total vendido</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $moeda($totais['total_vendido']) }}</p>
            <p class="text-xs text-gray-400 mt-1">
                Descontos {{ $moeda($totais['descontos']) }} · Cancelado {{ $moeda($totais['cancelado']) }}
            </p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Dinheiro esperado × informado</p>
            <p class="text-lg font-bold text-gray-800 mt-1">{{ $moeda($totais['esperado']) }}</p>
            <p class="text-sm text-gray-600">{{ $moeda($totais['informado']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border {{ $totais['com_diferenca'] ? 'border-red-200' : 'border-gray-200' }} p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Diferença na gaveta</p>
            <p class="text-2xl font-bold {{ $corDiferenca($totais['diferenca']) }} mt-1">{{ $moeda($totais['diferenca']) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $totais['com_diferenca'] }} caixa(s) com sobra ou falta</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wide">
                <tr>
                    <th class="text-left px-4 py-3">Caixa nº</th>
                    <th class="text-left px-4 py-3">Fechamento</th>
                    <th class="text-left px-4 py-3">PDV</th>
                    <th class="text-left px-4 py-3">Operador</th>
                    <th class="text-right px-4 py-3">Vendas</th>
                    <th class="text-right px-4 py-3">Total vendido</th>
                    <th class="text-right px-4 py-3">Esperado (dinheiro)</th>
                    <th class="text-right px-4 py-3">Informado</th>
                    <th class="text-right px-4 py-3">Diferença</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($caixas as $caixa)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ str_pad($caixa->id, 6, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-4 py-3">
                            {{ $caixa->data_fechamento?->format('d/m/Y H:i') }}
                            <span class="block text-xs text-gray-400">aberto {{ $caixa->data_abertura?->format('d/m H:i') }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $caixa->pdv->nome ?? '-' }}</td>
                        <td class="px-4 py-3">
                            {{ $caixa->operador?->codigo ? str_pad($caixa->operador->codigo, 4, '0', STR_PAD_LEFT) . ' - ' : '' }}{{ $caixa->operador->name ?? 'Operador #' . $caixa->operador_id }}
                        </td>
                        <td class="px-4 py-3 text-right">{{ (int) $caixa->qtd_vendas }}</td>
                        <td class="px-4 py-3 text-right">{{ $moeda($caixa->total_vendido) }}</td>
                        <td class="px-4 py-3 text-right">{{ $moeda($caixa->dinheiro_esperado) }}</td>
                        <td class="px-4 py-3 text-right">{{ $caixa->valor_fechamento_informado !== null ? $moeda($caixa->valor_fechamento_informado) : '-' }}</td>
                        <td class="px-4 py-3 text-right font-semibold {{ $corDiferenca($caixa->diferenca) }}">{{ $moeda($caixa->diferenca) }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('relatorios.caixa.fechamento.imprimir', ['caixa' => $caixa, 'auto' => 1]) }}" target="_blank"
                               class="inline-block px-3 py-1.5 text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
                                Imprimir 2ª via
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-10 text-center text-gray-400">Nenhum fechamento de caixa no período.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $caixas->links() }}</div>

    <p class="text-xs text-gray-400 mt-4">
        Esperado (dinheiro) = fundo de troco + vendas recebidas em dinheiro − troco devolvido. Vendas canceladas não entram.
        Diferença positiva é sobra; negativa é falta.
    </p>
@endsection
