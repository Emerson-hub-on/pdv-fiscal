@extends('layouts.app')

@section('titulo', 'Montagem NF-e a partir de NFC-e')

@section('conteudo')
@php
    $rotulosStatus = [
        'emitida'      => ['Autorizada', 'bg-green-100 text-green-800'],
        'contingencia' => ['Contingência', 'bg-amber-100 text-amber-800'],
        'cancelada'    => ['Cancelada', 'bg-red-100 text-red-700'],
        'pendente'     => ['Não autorizada', 'bg-gray-100 text-gray-700'],
    ];
@endphp

<div class="bg-white rounded-lg shadow overflow-visible">
    <form method="GET" action="{{ route('montagem-nfe.nfce.index') }}" id="form-busca-nfce"
          class="flex flex-col gap-3 p-4 border-b border-gray-100">
        <input type="hidden" name="modo" id="campo-modo" value="{{ $modo }}">

        <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-lg font-semibold mr-1">Montagem NF-e — a partir de NFC-e</h1>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <div class="flex rounded-lg border border-gray-300 overflow-hidden text-sm">
                <button type="button" onclick="trocarModo('cupom')"
                        class="px-3 py-1.5 {{ $modo === 'cupom' ? 'bg-[#10284A] text-amber-50' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                    Série e número
                </button>
                <button type="button" onclick="trocarModo('data')"
                        class="px-3 py-1.5 border-l border-gray-300 {{ $modo === 'data' ? 'bg-[#10284A] text-amber-50' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                    Data
                </button>
            </div>

            @if ($modo === 'cupom')
                <div>
                    <label for="campo-serie" class="block text-xs text-gray-500 mb-1">Série</label>
                    <input type="number" name="serie" id="campo-serie" min="0" max="999" required
                           value="{{ request('serie', $seriePadrao) }}"
                           class="w-24 border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label for="campo-numero" class="block text-xs text-gray-500 mb-1">Número do cupom</label>
                    <input type="number" name="numero" id="campo-numero" min="1" required
                           value="{{ request('numero') }}"
                           class="w-40 border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                </div>
                <button type="submit"
                        class="bg-gray-800 text-white rounded-lg px-4 py-1.5 text-sm hover:bg-gray-700">
                    Buscar
                </button>
            @else
                <div>
                    <label for="campo-data" class="block text-xs text-gray-500 mb-1">Data da venda</label>
                    <input type="date" name="data" id="campo-data" value="{{ $data }}"
                           onchange="this.form.submit()"
                           class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                </div>
            @endif
        </div>
    </form>

    @if ($resultadoCupom && $resultadoCupom['mensagem'])
        @php
            $corAviso = $resultadoCupom['situacao'] === 'nao_sincronizada'
                ? 'bg-amber-50 border-amber-300 text-amber-900'
                : 'bg-red-50 border-red-200 text-red-800';
        @endphp
        <div class="m-4 border rounded-lg px-4 py-3 text-sm {{ $corAviso }}">
            @if ($resultadoCupom['situacao'] === 'nao_sincronizada')
                <p class="font-semibold">Venda não sincronizada</p>
            @endif
            <p>{{ $resultadoCupom['mensagem'] }}</p>
        </div>
    @endif

    @if ($pendentes->isNotEmpty())
        <div class="m-4 border border-amber-300 bg-amber-50 rounded-lg px-4 py-3 text-sm text-amber-900">
            <p class="font-semibold">Venda não sincronizada</p>
            <p>
                {{ $pendentes->count() === 1 ? 'Há 1 venda deste dia' : "Há {$pendentes->count()} vendas deste dia" }}
                ainda só no caixa (aparecem na lista abaixo). Use "Atualizar Caixa" para enviá-las ao servidor antes de montar a NF-e.
            </p>
        </div>
    @endif

    @if ($modo === 'data' || $vendas->isNotEmpty())
        <div class="overflow-auto max-h-[calc(100vh-16rem)] rounded-b-lg">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-[#10284A] text-amber-50 text-xs uppercase">
                    <tr>
                        <th class="text-left px-4 py-2">Número</th>
                        <th class="text-left px-4 py-2">Série</th>
                        <th class="text-left px-4 py-2">Data/hora</th>
                        <th class="text-left px-4 py-2">Cliente</th>
                        <th class="text-right px-4 py-2">Total</th>
                        <th class="text-left px-4 py-2">Situação</th>
                        <th class="text-right px-4 py-2">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($pendentes as $p)
                        <tr class="bg-amber-50/60">
                            <td class="px-4 py-2 font-medium">{{ $p->numero_nfce }}</td>
                            <td class="px-4 py-2">{{ $p->serie_nfce }}</td>
                            <td class="px-4 py-2">{{ \Illuminate\Support\Carbon::parse($p->vendida_em)->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2 text-gray-400">—</td>
                            <td class="px-4 py-2 text-right">R$ {{ number_format((float) $p->total, 2, ',', '.') }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium bg-amber-200 text-amber-900">Não sincronizada</span>
                            </td>
                            <td class="px-4 py-2 text-right text-xs text-amber-800">Sincronize o caixa</td>
                        </tr>
                    @endforeach

                    @forelse ($vendas as $venda)
                        @php
                            [$rotulo, $cor] = $rotulosStatus[$venda->status] ?? [ucfirst((string) $venda->status), 'bg-gray-100 text-gray-700'];
                            $bloqueio = $bloqueios[$venda->id] ?? null;
                            $nfe = $nfesGeradas[$venda->id] ?? null;
                            $documento = $venda->cliente?->cpf_cnpj_formatado ?? $venda->cpf_na_nota;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 font-medium">{{ $venda->numero_nfce }}</td>
                            <td class="px-4 py-2">{{ $venda->serie_nfce }}</td>
                            <td class="px-4 py-2">{{ $venda->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2">
                                @if ($venda->cliente)
                                    {{ $venda->cliente->nome }}
                                    <span class="text-gray-400">{{ $documento }}</span>
                                @elseif ($documento)
                                    <span class="text-gray-500">CPF/CNPJ {{ $documento }}</span>
                                @else
                                    <span class="text-gray-400">Consumidor não identificado</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right">R$ {{ number_format((float) $venda->total, 2, ',', '.') }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $cor }}">{{ $rotulo }}</span>
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                @if ($nfe)
                                    <a href="{{ $nfe->status === 'rascunho' ? route('notasfiscais.edit', $nfe) : route('notasfiscais.show', $nfe) }}"
                                       title="{{ $bloqueio }}"
                                       class="inline-block border border-gray-300 rounded-lg px-3 py-1 text-xs hover:bg-gray-50">
                                        Ver NF-e {{ $nfe->numero ? 'nº ' . $nfe->numero : '(rascunho)' }}
                                    </a>
                                @elseif ($bloqueio)
                                    <span class="text-xs text-gray-400" title="{{ $bloqueio }}">Indisponível</span>
                                @else
                                    <a href="{{ route('montagem-nfe.nfce.montar', $venda) }}"
                                       class="inline-block bg-gray-700 text-amber-50 rounded-lg px-3 py-1 text-xs hover:bg-gray-500">
                                        Montar NF-e
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @if ($bloqueio && $modo === 'cupom')
                            <tr>
                                <td colspan="7" class="px-4 pb-3 text-xs text-red-700">{{ $bloqueio }}</td>
                            </tr>
                        @endif
                    @empty
                        @if ($pendentes->isEmpty())
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                                    Nenhuma NFC-e em {{ \Illuminate\Support\Carbon::parse($data)->format('d/m/Y') }}.
                                </td>
                            </tr>
                        @endif
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif (!$resultadoCupom)
        <p class="px-4 py-8 text-center text-sm text-gray-400">Informe a série e o número do cupom (NFC-e) e clique em Buscar.</p>
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
