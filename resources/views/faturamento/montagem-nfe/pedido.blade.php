@extends('layouts.app')

@section('titulo', 'Montagem NF-e a partir de pedido')

@section('conteudo')
@php
    extract(config('estilo.formulario'));

    $rotulosStatus = [
        'finalizado' => ['Em aberto', 'bg-green-100 text-green-800'],
        'emitido'    => ['Emitido', 'bg-slate-200 text-slate-700'],
        'cancelado'  => ['Cancelado', 'bg-red-100 text-red-700'],
    ];
@endphp

<div class="flex flex-col gap-4 w-full max-w-6xl mt-2 mb-6">
    <div class="{{ $cartaoCls }}">
        <x-form.cabecalho rotulo="Faturamento · Montagem NF-e" titulo="A partir de pedido de venda" />

        <form method="GET" action="{{ route('montagem-nfe.pedido.index') }}" class="p-6 flex flex-wrap items-end gap-4">
            <input type="hidden" name="modo" value="{{ $modo }}">

            <div>
                <span class="{{ $labelCls }}">Buscar por</span>
                <div class="flex rounded-xl ring-1 ring-slate-200 overflow-hidden text-sm">
                    <button type="button" onclick="trocarModo('numero')"
                            class="px-4 py-2 font-medium transition {{ $modo === 'numero' ? 'bg-[#10284A] text-amber-50' : 'bg-white text-slate-600 hover:bg-slate-50' }}">
                        Número do pedido
                    </button>
                    <button type="button" onclick="trocarModo('data')"
                            class="px-4 py-2 font-medium border-l border-slate-200 transition {{ $modo === 'data' ? 'bg-[#10284A] text-amber-50' : 'bg-white text-slate-600 hover:bg-slate-50' }}">
                        Data
                    </button>
                </div>
            </div>

            @if ($modo === 'numero')
                <div class="w-48">
                    <label for="campo-numero" class="{{ $labelCls }}">Número do pedido</label>
                    <input type="number" name="numero" id="campo-numero" min="1" required
                           value="{{ request('numero') }}" class="{{ $inputCls }}">
                </div>
                <button type="submit" class="{{ $botaoAcaoCls }}">
                    <x-form.icone nome="busca" /> Buscar
                </button>
            @else
                <div>
                    <label for="campo-data" class="{{ $labelCls }}">Data do pedido</label>
                    <input type="date" name="data" id="campo-data" value="{{ $data }}"
                           onchange="this.form.submit()" class="{{ $inputCls }}">
                </div>
            @endif
        </form>
    </div>

    @if ($naoEncontrado)
        <div class="flex items-start gap-3 bg-red-50 ring-1 ring-red-200 rounded-xl px-4 py-3 text-sm text-red-800">
            <x-form.icone nome="info" class="mt-0.5 shrink-0" />
            <p>{{ $naoEncontrado }}</p>
        </div>
    @endif

    @if ($modo === 'data' || $pedidos->isNotEmpty())
        <div class="{{ $cartaoCls }} overflow-hidden">
            <div class="overflow-auto max-h-[calc(100vh-18rem)]">
                <table class="w-full text-sm text-slate-700">
                    <thead class="{{ $theadCls }} sticky top-0 z-10">
                        <tr>
                            <th class="text-left px-4 py-2.5">Pedido</th>
                            <th class="text-left px-4 py-2.5">Data</th>
                            <th class="text-left px-4 py-2.5">Cliente</th>
                            <th class="text-right px-4 py-2.5">Valor</th>
                            <th class="text-left px-4 py-2.5">Situação</th>
                            <th class="text-right px-4 py-2.5">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($pedidos as $pedido)
                            @php
                                [$rotulo, $cor] = $rotulosStatus[$pedido->status] ?? [ucfirst((string) $pedido->status), 'bg-slate-100 text-slate-700'];
                                $bloqueio = $bloqueios[$pedido->id] ?? null;
                                $nfe = $nfesGeradas[$pedido->id] ?? null;
                                $venda = $vendasCaixa[$pedido->id] ?? null;
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2.5 font-semibold font-mono">{{ $pedido->numeroExibicao() }}</td>
                                <td class="px-4 py-2.5">{{ $pedido->data_pedido?->format('d/m/Y') }}</td>
                                <td class="px-4 py-2.5">
                                    @if ($pedido->cliente)
                                        {{ $pedido->cliente->nome }}
                                        <span class="text-slate-400">{{ $pedido->cliente->cpf_cnpj_formatado }}</span>
                                    @else
                                        <span class="text-slate-400">Sem cliente</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right font-medium">R$ {{ number_format((float) $pedido->valor_total, 2, ',', '.') }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $cor }}">{{ $rotulo }}</span>
                                    @if ($venda)
                                        <span class="block text-xs text-slate-500 mt-0.5">NFC-e nº {{ $venda->numero_nfce }} no caixa</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                    @if ($nfe)
                                        <a href="{{ $nfe->status === 'rascunho' ? route('notasfiscais.edit', $nfe) : route('notasfiscais.show', $nfe) }}"
                                           title="{{ $bloqueio }}" class="text-xs {{ $botaoSecundarioCls }}">
                                            Ver NF-e {{ $nfe->numero ? 'nº ' . $nfe->numero : '(rascunho)' }}
                                        </a>
                                    @elseif ($venda)
                                        <a href="{{ route('montagem-nfe.nfce.index', ['modo' => 'cupom', 'serie' => $venda->serie_nfce, 'numero' => $venda->numero_nfce]) }}"
                                           title="{{ $bloqueio }}" class="text-xs {{ $botaoSecundarioCls }}">
                                            Montar pela NFC-e
                                        </a>
                                    @elseif ($bloqueio)
                                        <span class="text-xs text-slate-400" title="{{ $bloqueio }}">Indisponível</span>
                                    @else
                                        <a href="{{ route('montagem-nfe.pedido.montar', $pedido) }}" class="text-xs {{ $botaoAcaoCls }} py-1.5!">
                                            <x-form.icone nome="documento" tamanho="w-3.5 h-3.5" /> Montar NF-e
                                        </a>
                                    @endif
                                </td>
                            </tr>
                            @if ($bloqueio && $modo === 'numero')
                                <tr>
                                    <td colspan="6" class="px-4 pb-3">
                                        <div class="flex items-start gap-2 text-xs {{ str_starts_with($bloqueio, 'Venda não sincronizada') ? 'text-amber-800' : 'text-red-700' }}">
                                            <x-form.icone nome="info" tamanho="w-3.5 h-3.5" class="mt-0.5 shrink-0" />
                                            <span>{{ $bloqueio }}</span>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-slate-400">
                                    Nenhum pedido de venda em {{ \Illuminate\Support\Carbon::parse($data)->format('d/m/Y') }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif (!$naoEncontrado)
        <p class="px-4 py-8 text-center text-sm text-slate-400">Informe o número do pedido de venda e clique em Buscar.</p>
    @endif
</div>

<script>
function trocarModo(modo) {
    const url = new URL(@json(route('montagem-nfe.pedido.index')));
    url.searchParams.set('modo', modo);
    window.location = url.toString();
}

document.getElementById('campo-numero')?.focus();
</script>
@endsection
