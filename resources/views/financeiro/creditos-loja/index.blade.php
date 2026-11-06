@extends('layouts.app')

@section('titulo', 'Créditos em loja')

@section('conteudo')
@php
    extract(config('estilo.formulario'));

    $rotulosStatus = [
        'ativo'     => ['Com saldo', 'bg-green-100 text-green-800'],
        'esgotado'  => ['Usado', 'bg-slate-200 text-slate-700'],
        'cancelado' => ['Cancelado', 'bg-red-100 text-red-700'],
    ];
    $moeda = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
@endphp

<div class="flex flex-col gap-4 w-full max-w-6xl mt-2 mb-6">
    <div class="{{ $cartaoCls }}">
        <x-form.cabecalho rotulo="Financeiro" titulo="Créditos em loja">
            <div class="text-right">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Saldo em aberto</p>
                <p class="text-lg font-bold text-amber-50">{{ $moeda($totalEmAberto) }}</p>
            </div>
        </x-form.cabecalho>

        <form method="GET" action="{{ route('creditos-loja.index') }}" class="p-6 flex flex-wrap items-end gap-4">
            <div class="w-80">
                <label for="campo-busca" class="{{ $labelCls }}">Cliente, CPF/CNPJ ou código do vale</label>
                <input type="text" name="busca" id="campo-busca" value="{{ $busca }}" placeholder="Ex.: Maria ou VT000071" class="{{ $inputCls }}">
            </div>
            <div class="w-48">
                <label for="campo-situacao" class="{{ $labelCls }}">Situação</label>
                <select name="situacao" id="campo-situacao" onchange="this.form.submit()" class="{{ $inputCls }}">
                    <option value="com_saldo" @selected($situacao === 'com_saldo')>Com saldo</option>
                    <option value="esgotado" @selected($situacao === 'esgotado')>Usados</option>
                    <option value="cancelado" @selected($situacao === 'cancelado')>Cancelados</option>
                    <option value="todos" @selected($situacao === 'todos')>Todos</option>
                </select>
            </div>
            <button type="submit" class="{{ $botaoAcaoCls }}"><x-form.icone nome="busca" /> Buscar</button>
            <p class="text-xs text-slate-500 basis-full">
                O crédito é gerado quando a NF-e de entrada de uma troca é autorizada. Pode ser usado no caixa (forma "Crédito em loja")
                ou na NF-e de venda. Consumidor não identificado usa pelo código do vale-troca.
            </p>
        </form>
    </div>

    <div class="{{ $cartaoCls }} overflow-hidden">
        <div class="overflow-auto">
            <table class="w-full text-sm text-slate-700">
                <thead class="{{ $theadCls }}">
                    <tr>
                        <th class="text-left px-4 py-2.5">Vale</th>
                        <th class="text-left px-4 py-2.5">Cliente</th>
                        <th class="text-left px-4 py-2.5">Origem</th>
                        <th class="text-right px-4 py-2.5">Valor</th>
                        <th class="text-right px-4 py-2.5">Saldo</th>
                        <th class="text-left px-4 py-2.5">Situação</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($creditos as $credito)
                        @php [$rotulo, $cor] = $rotulosStatus[$credito->status] ?? [$credito->status, 'bg-slate-100 text-slate-700']; @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-2.5 font-mono font-semibold">{{ $credito->codigo }}</td>
                            <td class="px-4 py-2.5">
                                @if ($credito->cliente)
                                    {{ $credito->cliente->nome }} <span class="text-slate-400">{{ $credito->cliente->cpf_cnpj_formatado }}</span>
                                @else
                                    <span class="text-slate-500">Consumidor não identificado (usar pelo código)</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                @if ($credito->notaFiscal)
                                    <a href="{{ route('notasfiscais.show', $credito->notaFiscal) }}" class="text-[#10284A] hover:underline">
                                        Troca · NF-e de entrada nº {{ $credito->notaFiscal->numero }}
                                    </a>
                                    <span class="block text-xs text-slate-400">{{ $credito->created_at->format('d/m/Y H:i') }}</span>
                                @else
                                    <span class="text-slate-400">{{ $credito->created_at->format('d/m/Y H:i') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right">{{ $moeda($credito->valor) }}</td>
                            <td class="px-4 py-2.5 text-right font-bold text-[#10284A]">{{ $moeda($credito->saldo) }}</td>
                            <td class="px-4 py-2.5">
                                <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $cor }}">{{ $rotulo }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <button type="button" onclick="document.getElementById('mov-{{ $credito->id }}').classList.toggle('hidden')"
                                        class="text-xs {{ $botaoSecundarioCls }} py-1.5!">Histórico</button>
                            </td>
                        </tr>
                        <tr id="mov-{{ $credito->id }}" class="hidden bg-slate-50">
                            <td colspan="7" class="px-6 py-3">
                                <table class="w-full text-xs">
                                    <tbody class="divide-y divide-slate-200">
                                        @foreach ($credito->movimentos as $mov)
                                            <tr>
                                                <td class="py-1.5 w-36 text-slate-500">{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                                                <td class="py-1.5 w-32 font-medium">{{ \App\Models\CreditoLojaMovimento::ROTULOS[$mov->tipo] ?? $mov->tipo }}</td>
                                                <td class="py-1.5">
                                                    {{ $mov->descricao }}
                                                    @if ($mov->notaFiscal && $mov->tipo !== 'credito')
                                                        · <a href="{{ route('notasfiscais.show', $mov->notaFiscal) }}" class="text-[#10284A] hover:underline">ver NF-e</a>
                                                    @endif
                                                </td>
                                                <td class="py-1.5 w-32 text-right font-semibold {{ in_array($mov->tipo, ['uso', 'cancelamento'], true) ? 'text-red-700' : 'text-green-700' }}">
                                                    {{ in_array($mov->tipo, ['uso', 'cancelamento'], true) ? '−' : '+' }} {{ $moeda($mov->valor) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-slate-400">Nenhum crédito em loja encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($creditos->hasPages())
            <div class="px-4 py-3 border-t border-slate-100">{{ $creditos->links() }}</div>
        @endif
    </div>
</div>
@endsection
