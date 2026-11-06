@extends('layouts.app')

@section('titulo', 'Montagem NF-e a partir de NFC-e')

@section('conteudo')
@php
    extract(config('estilo.formulario'));

    $rotulosStatus = [
        'emitida'      => ['Autorizada', 'bg-green-100 text-green-800'],
        'contingencia' => ['Contingência', 'bg-amber-100 text-amber-800'],
        'cancelada'    => ['Cancelada', 'bg-red-100 text-red-700'],
        'pendente'     => ['Não autorizada', 'bg-slate-100 text-slate-700'],
    ];
@endphp

<div class="flex flex-col gap-4 w-full max-w-6xl mt-2 mb-6">
    <div class="{{ $cartaoCls }}">
        <x-form.cabecalho rotulo="Faturamento · Montagem NF-e" titulo="A partir de NFC-e" />

        <form method="GET" action="{{ route('montagem-nfe.nfce.index') }}" id="form-busca-nfce" class="p-6 flex flex-wrap items-end gap-4">
            <input type="hidden" name="modo" id="campo-modo" value="{{ $modo }}">

            <div>
                <span class="{{ $labelCls }}">Buscar por</span>
                <div class="flex rounded-xl ring-1 ring-slate-200 overflow-hidden text-sm">
                    <button type="button" onclick="trocarModo('cupom')"
                            class="px-4 py-2 font-medium transition {{ $modo === 'cupom' ? 'bg-[#10284A] text-amber-50' : 'bg-white text-slate-600 hover:bg-slate-50' }}">
                        Série e número
                    </button>
                    <button type="button" onclick="trocarModo('data')"
                            class="px-4 py-2 font-medium border-l border-slate-200 transition {{ $modo === 'data' ? 'bg-[#10284A] text-amber-50' : 'bg-white text-slate-600 hover:bg-slate-50' }}">
                        Data
                    </button>
                </div>
            </div>

            @if ($modo === 'cupom')
                <div class="w-24">
                    <label for="campo-serie" class="{{ $labelCls }}">Série</label>
                    <input type="number" name="serie" id="campo-serie" min="0" max="999" required
                           value="{{ request('serie', $seriePadrao) }}" class="{{ $inputCls }}">
                </div>
                <div class="w-44">
                    <label for="campo-numero" class="{{ $labelCls }}">Número do cupom</label>
                    <input type="number" name="numero" id="campo-numero" min="1" required
                           value="{{ request('numero') }}" class="{{ $inputCls }}">
                </div>
                <button type="submit" class="{{ $botaoAcaoCls }}">
                    <x-form.icone nome="busca" /> Buscar
                </button>
            @else
                <div>
                    <label for="campo-data" class="{{ $labelCls }}">Data da venda</label>
                    <input type="date" name="data" id="campo-data" value="{{ $data }}"
                           onchange="this.form.submit()" class="{{ $inputCls }}">
                </div>
            @endif
        </form>
    </div>

    @if ($resultadoCupom && $resultadoCupom['mensagem'])
        @php $naoSincronizada = $resultadoCupom['situacao'] === 'nao_sincronizada'; @endphp
        <div class="flex items-start gap-3 rounded-xl px-4 py-3 text-sm {{ $naoSincronizada ? 'bg-amber-50 ring-1 ring-amber-300 text-amber-900' : 'bg-red-50 ring-1 ring-red-200 text-red-800' }}">
            <x-form.icone nome="info" class="mt-0.5 shrink-0" />
            <div>
                @if ($naoSincronizada)
                    <p class="font-semibold">Venda não sincronizada</p>
                @endif
                <p>{{ $resultadoCupom['mensagem'] }}</p>
            </div>
        </div>
    @endif

    @if ($pendentes->isNotEmpty())
        <div class="flex items-start gap-3 bg-amber-50 ring-1 ring-amber-300 rounded-xl px-4 py-3 text-sm text-amber-900">
            <x-form.icone nome="info" class="mt-0.5 shrink-0" />
            <div>
                <p class="font-semibold">Venda não sincronizada</p>
                <p>
                    {{ $pendentes->count() === 1 ? 'Há 1 venda deste dia' : "Há {$pendentes->count()} vendas deste dia" }}
                    ainda só no caixa (aparecem na lista abaixo). Use "Atualizar Caixa" para enviá-las ao servidor antes de montar a NF-e.
                </p>
            </div>
        </div>
    @endif

    @if ($modo === 'data' || $vendas->isNotEmpty())
        <div class="{{ $cartaoCls }} overflow-hidden">
            <div class="overflow-auto max-h-[calc(100vh-18rem)]">
                <table class="w-full text-sm text-slate-700">
                    <thead class="{{ $theadCls }} sticky top-0 z-10">
                        <tr>
                            <th class="text-left px-4 py-2.5">Número</th>
                            <th class="text-left px-4 py-2.5">Série</th>
                            <th class="text-left px-4 py-2.5">Data/hora</th>
                            <th class="text-left px-4 py-2.5">Cliente</th>
                            <th class="text-right px-4 py-2.5">Total</th>
                            <th class="text-left px-4 py-2.5">Situação</th>
                            <th class="text-right px-4 py-2.5">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($pendentes as $p)
                            <tr class="bg-amber-50/60">
                                <td class="px-4 py-2.5 font-semibold font-mono">{{ $p->numero_nfce }}</td>
                                <td class="px-4 py-2.5">{{ $p->serie_nfce }}</td>
                                <td class="px-4 py-2.5">{{ \Illuminate\Support\Carbon::parse($p->vendida_em)->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-2.5 text-slate-400">—</td>
                                <td class="px-4 py-2.5 text-right font-medium">R$ {{ number_format((float) $p->total, 2, ',', '.') }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium bg-amber-200 text-amber-900">Não sincronizada</span>
                                </td>
                                <td class="px-4 py-2.5 text-right text-xs text-amber-800">Sincronize o caixa</td>
                            </tr>
                        @endforeach

                        @forelse ($vendas as $venda)
                            @php
                                [$rotulo, $cor] = $rotulosStatus[$venda->status] ?? [ucfirst((string) $venda->status), 'bg-slate-100 text-slate-700'];
                                $bloqueio = $bloqueios[$venda->id] ?? null;
                                $nfe = $nfesGeradas[$venda->id] ?? null;
                                $documento = $venda->cliente?->cpf_cnpj_formatado ?? $venda->cpf_na_nota;
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2.5 font-semibold font-mono">{{ $venda->numero_nfce }}</td>
                                <td class="px-4 py-2.5">{{ $venda->serie_nfce }}</td>
                                <td class="px-4 py-2.5">{{ $venda->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-2.5">
                                    @if ($venda->cliente)
                                        {{ $venda->cliente->nome }}
                                        <span class="text-slate-400">{{ $documento }}</span>
                                    @elseif ($documento)
                                        <span class="text-slate-500">CPF/CNPJ {{ $documento }}</span>
                                    @else
                                        <span class="text-slate-400">Consumidor não identificado</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right font-medium">R$ {{ number_format((float) $venda->total, 2, ',', '.') }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $cor }}">{{ $rotulo }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                    @if ($nfe)
                                        <a href="{{ $nfe->status === 'rascunho' ? route('notasfiscais.edit', $nfe) : route('notasfiscais.show', $nfe) }}"
                                           title="{{ $bloqueio }}" class="text-xs {{ $botaoSecundarioCls }}">
                                            Ver NF-e {{ $nfe->numero ? 'nº ' . $nfe->numero : '(rascunho)' }}
                                        </a>
                                    @elseif ($bloqueio)
                                        <span class="text-xs text-slate-400" title="{{ $bloqueio }}">Indisponível</span>
                                    @else
                                        <a href="{{ route('montagem-nfe.nfce.montar', $venda) }}" class="text-xs {{ $botaoAcaoCls }} py-1.5!">
                                            <x-form.icone nome="documento" tamanho="w-3.5 h-3.5" /> Montar NF-e
                                        </a>
                                    @endif
                                </td>
                            </tr>
                            @if ($bloqueio && $modo === 'cupom')
                                <tr>
                                    <td colspan="7" class="px-4 pb-3">
                                        <div class="flex items-start gap-2 text-xs text-red-700">
                                            <x-form.icone nome="info" tamanho="w-3.5 h-3.5" class="mt-0.5 shrink-0" />
                                            <span>{{ $bloqueio }}</span>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            @if ($pendentes->isEmpty())
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-slate-400">
                                        Nenhuma NFC-e em {{ \Illuminate\Support\Carbon::parse($data)->format('d/m/Y') }}.
                                    </td>
                                </tr>
                            @endif
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif (!$resultadoCupom)
        <p class="px-4 py-8 text-center text-sm text-slate-400">Informe a série e o número do cupom (NFC-e) e clique em Buscar.</p>
    @endif
</div>

<script>
function trocarModo(modo) {
    const url = new URL(@json(route('montagem-nfe.nfce.index')));
    url.searchParams.set('modo', modo);
    window.location = url.toString();
}

// Série já preenchida: o cursor vai direto para o número
(document.getElementById('campo-serie')?.value ? document.getElementById('campo-numero') : document.getElementById('campo-serie'))?.focus();
</script>
@endsection
