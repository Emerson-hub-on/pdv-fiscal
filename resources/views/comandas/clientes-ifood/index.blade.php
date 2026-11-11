@extends('layouts.app')

@section('titulo', 'Clientes iFood')
@section('body-class', 'conteudo-largo')

@section('breadcrumb')
    <span>Comandas</span>
    <span class="text-gray-300">›</span>
    <span>Clientes</span>
    <span class="text-gray-300">›</span>
    <span class="text-gray-700 font-medium">Clientes iFood</span>
@endsection

@php
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
@endphp

@section('conteudo')
    {{-- Resumo --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-gray-500">Clientes</p>
            <p class="text-lg font-semibold text-gray-800">{{ number_format($resumo->clientes, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-gray-500">Novos no mês</p>
            <p class="text-lg font-semibold text-blue-700">{{ number_format($resumo->novos_mes, 0, ',', '.') }}</p>
            <p class="text-xs text-gray-400">primeiro pedido em {{ now()->translatedFormat('F') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-gray-500">Recorrentes</p>
            <p class="text-lg font-semibold text-green-700">{{ number_format($resumo->recorrentes, 0, ',', '.') }}</p>
            <p class="text-xs text-gray-400">2 pedidos ou mais</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-gray-500">Ticket médio</p>
            <p class="text-lg font-semibold text-gray-800">{{ $dinheiro($resumo->ticket) }}</p>
            <p class="text-xs text-gray-400">pedidos não cancelados</p>
        </div>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('clientes-ifood.index') }}" class="flex flex-wrap items-end gap-3 mb-4">
        <input type="text" name="busca" value="{{ $busca }}" placeholder="Nome, CPF ou nº do pedido" autocomplete="off"
               class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm w-72 focus:ring-2 focus:ring-slate-800 outline-none">
        <select name="recorrencia" class="bg-white border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
            <option value="todos" @selected($recorrencia === 'todos')>Todos os clientes</option>
            <option value="novos" @selected($recorrencia === 'novos')>Só 1 pedido</option>
            <option value="recorrentes" @selected($recorrencia === 'recorrentes')>Recorrentes (2+)</option>
        </select>
        <select name="ordenar" class="bg-white border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
            @foreach ($ordens as $valor => $rotulo)
                <option value="{{ $valor }}" @selected($ordenar === $valor)>{{ $rotulo }}</option>
            @endforeach
        </select>
        <button class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2 rounded-lg font-semibold text-sm transition">Buscar</button>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead class="bg-[#10284A] text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Cliente</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">CPF</th>
                        <th class="px-4 py-3 text-center font-medium text-amber-50">Pedidos aqui</th>
                        <th class="px-4 py-3 text-center font-medium text-amber-50">Pedidos na loja (iFood)</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Total gasto</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Ticket médio</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Último pedido</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Bairro</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($clientes as $c)
                        <tr class="hover:bg-gray-100 transition cursor-pointer" onclick="location.href='{{ route('clientes-ifood.show', $c) }}'">
                            <td class="px-4 py-3">
                                <a href="{{ route('clientes-ifood.show', $c) }}" class="font-medium text-gray-800 hover:text-blue-600 hover:underline">{{ $c->nome ?: 'Cliente sem nome' }}</a>
                                <p class="text-xs text-gray-500">
                                    {{ $c->segmento() }}
                                    @if ($c->pedidos_validos <= 1)
                                        · <span class="text-blue-600 font-medium">1º pedido</span>
                                    @endif
                                    @if ($c->pedidos_cancelados)
                                        · <span class="text-red-600">{{ $c->pedidos_cancelados }} cancelado(s)</span>
                                    @endif
                                </p>
                            </td>
                            <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $c->cpfFormatado() ?: '—' }}</td>
                            <td class="px-4 py-3 text-center font-semibold text-gray-800">{{ $c->pedidos_validos }}</td>
                            <td class="px-4 py-3 text-center text-gray-600" title="Quantos pedidos o iFood conta deste cliente na sua loja">{{ $c->pedidos_na_loja_ifood ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-800">{{ $dinheiro($c->total_gasto) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $c->pedidos_validos ? $dinheiro($c->total_gasto / $c->pedidos_validos) : '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $c->ultimo_pedido_em?->format('d/m/Y H:i') ?? '—' }}
                                @if ($c->ultimo_pedido_em)
                                    <p class="text-xs text-gray-400">{{ $c->ultimo_pedido_em->diffForHumans() }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ collect([$c->bairro, $c->cidade])->filter()->implode(' · ') ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">Nenhum cliente do iFood encontrado. Os clientes entram sozinhos a cada pedido do iFood.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $clientes->links() }}</div>
    <p class="mt-2 text-xs text-gray-400">"Pedidos aqui" conta os pedidos recebidos por este sistema (sem os cancelados). "Pedidos na loja (iFood)" é o número que o iFood informa no último pedido do cliente.</p>
@endsection
