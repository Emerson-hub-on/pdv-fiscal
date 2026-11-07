@extends('layouts.app')

@section('titulo', 'Formas de pagamento do caixa')

@section('conteudo')
@php
    extract(config('estilo.formulario'));
    $meios = \App\Support\FormasCaixa::MEIOS + ['05' => 'Crédito Loja (convênio)', '21' => 'Crédito em Loja (troca)'];
    $podeAlterar = auth()->user()?->podeAlterar('pdvs');
@endphp

    <x-form.cabecalho solto rotulo="Cadastros · Caixa" titulo="Formas de pagamento">
        @if ($podeAlterar)
            <a href="{{ route('formas-pagamento-caixa.create') }}" class="{{ $botaoAcaoCls }} bg-amber-50! text-[#10284A]! hover:bg-white!">
                <x-form.icone nome="mais" /> Nova forma de pagamento
            </a>
        @endif
    </x-form.cabecalho>

    <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900 mb-5">
        Estas são as opções da tela de <strong>Pagamento</strong> do caixa, na ordem abaixo. Formas inativas não aparecem no caixa.
        Depois de alterar, use <strong>Atualizar Caixa</strong> (F8 → Atualizar Caixa) em cada caixa para aplicar.
        O <strong>meio de pagamento</strong> é o código que vai na NFC-e (tPag) e no SPED. Clique numa forma para editar, ativar/inativar ou excluir.
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700 mb-5">{{ $errors->first() }}</div>
    @endif

    <div class="{{ $tabelaCaixaCls }} bg-white">
        <table class="w-full text-sm text-slate-700">
            <thead class="{{ $theadCls }}">
                <tr>
                    <th class="text-left px-4 py-3 w-16">Ordem</th>
                    <th class="text-left px-4 py-3">Descrição no caixa</th>
                    <th class="text-left px-4 py-3">Meio de pagamento (NFC-e)</th>
                    <th class="text-center px-4 py-3">Troco</th>
                    <th class="text-right px-4 py-3">Vendas</th>
                    <th class="text-left px-4 py-3">Situação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($formas as $forma)
                    {{-- Clique na linha abre a edição (lá ficam a situação e o excluir) --}}
                    <tr class="cursor-pointer hover:bg-amber-50 transition {{ $forma->ativo ? '' : 'opacity-60' }}"
                        onclick="window.location='{{ route('formas-pagamento-caixa.edit', $forma) }}'"
                        title="Clique para editar">
                        <td class="px-4 py-3 text-slate-500 font-mono">{{ $forma->ordem }}</td>
                        <td class="px-4 py-3">
                            <span class="font-semibold text-slate-800">{{ $forma->descricao }}</span>
                            @if ($forma->sistema)
                                <span class="ml-1.5 align-middle rounded bg-slate-100 text-slate-600 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide">Sistema</span>
                            @endif
                            @if (in_array($forma->tipo, \App\Support\FormasCaixa::TIPOS_ESPECIAIS, true))
                                <span class="block text-xs text-slate-400">
                                    {{ $forma->tipo === 'convenio' ? 'Abre o modal do convênio (limite de crédito do cliente)' : 'Abre o modal do vale-troca / crédito do cliente' }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-mono text-xs text-slate-500 mr-1">{{ $forma->meio_pagamento }}</span>
                            {{ $meios[$forma->meio_pagamento] ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($forma->permite_troco)
                                <span class="inline-block rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5 text-xs font-medium">Sim</span>
                            @else
                                <span class="text-slate-400 text-xs">Não</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-slate-500">{{ number_format($usos[$forma->codigo] ?? 0, 0, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            @if ($forma->ativo)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Ativa
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-600">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Inativa
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Nenhuma forma de pagamento cadastrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
