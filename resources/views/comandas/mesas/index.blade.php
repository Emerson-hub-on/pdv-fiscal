@extends('layouts.app')

@section('titulo', 'Mesas')

@section('conteudo')
@php
    extract(config('estilo.formulario'));
    $podeAlterar = auth()->user()?->podeAlterar('comandas');
    $filtrando = $busca !== '' || $status !== '' || $situacao !== 'ativas';
@endphp

    <x-form.cabecalho solto rotulo="Comandas · Mesas" titulo="Lista de Mesas">
        @if ($podeAlterar)
            <a href="{{ route('mesas.create') }}" class="{{ $botaoAcaoCls }} bg-amber-50! text-[#10284A]! hover:bg-white!">
                <x-form.icone nome="mais" /> Nova mesa
            </a>
        @endif
    </x-form.cabecalho>

    @if ($errors->has('mesa'))
        <div class="rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700 mb-5">{{ $errors->first('mesa') }}</div>
    @endif

    {{-- Cartões de resumo (mesas ativas) --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        @foreach ([
            ['Mesas ativas', $totais['mesas'], 'text-slate-800'],
            [\App\Models\Mesa::STATUS['disponivel']['rotulo'], $totais['disponivel'], 'text-green-700'],
            [\App\Models\Mesa::STATUS['aguardando']['rotulo'], $totais['aguardando'], $totais['aguardando'] ? 'text-amber-700' : 'text-slate-800'],
            [\App\Models\Mesa::STATUS['entregue']['rotulo'], $totais['entregue'], 'text-blue-700'],
        ] as [$rotulo, $valor, $cor])
            <div class="{{ $cartaoCls }} px-4 py-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $rotulo }}</p>
                <p class="text-2xl font-bold {{ $cor }}">{{ number_format($valor, 0, ',', '.') }}</p>
            </div>
        @endforeach
    </div>

    {{-- Filtros lado a lado --}}
    <form method="GET" action="{{ route('mesas.index') }}" class="{{ $cartaoCls }} p-4 mb-5">
        <div class="flex items-end gap-3">
            <div class="flex-1 min-w-0">
                <label class="{{ $labelCls }}">Buscar</label>
                <input type="search" name="busca" value="{{ $busca }}" placeholder="Número ou nome da mesa" class="{{ $inputCls }}">
            </div>
            <div class="w-56 shrink-0">
                <label class="{{ $labelCls }}">Status</label>
                <select name="status" class="{{ $inputCls }}" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    @foreach (\App\Models\Mesa::STATUS as $chave => $s)
                        <option value="{{ $chave }}" @selected($status === $chave)>{{ $s['rotulo'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-40 shrink-0">
                <label class="{{ $labelCls }}">Situação</label>
                <select name="situacao" class="{{ $inputCls }}" onchange="this.form.submit()">
                    <option value="ativas" @selected($situacao === 'ativas')>Ativas</option>
                    <option value="inativas" @selected($situacao === 'inativas')>Inativas</option>
                    <option value="todas" @selected($situacao === 'todas')>Todas</option>
                </select>
            </div>
            <button type="submit" class="{{ $botaoAcaoCls }} shrink-0">Filtrar</button>
        </div>
        @if ($filtrando)
            <div class="mt-2">
                <a href="{{ route('mesas.index') }}" class="text-xs text-slate-500 hover:text-[#10284A] underline">Limpar filtros</a>
            </div>
        @endif
    </form>

    @if ($mesas->isEmpty())
        <div class="{{ $cartaoCls }} px-6 py-14 text-center">
            <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 9h16M6 9v10m12-10v10M3 9l2-4h14l2 4"/>
            </svg>
            <p class="text-slate-500 text-sm">{{ $filtrando ? 'Nenhuma mesa encontrada com esses filtros.' : 'Nenhuma mesa cadastrada ainda.' }}</p>
            @if (!$filtrando && $podeAlterar)
                <a href="{{ route('mesas.create') }}" class="{{ $botaoAcaoCls }} mt-4">
                    <x-form.icone nome="mais" /> Cadastrar a primeira mesa
                </a>
            @endif
        </div>
    @else
        {{-- Tabs horizontais, como os itens do cardápio --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($mesas as $mesa)
                @php $s = $mesa->dadosStatus(); @endphp
                <{{ $podeAlterar ? 'a' : 'div' }} @if ($podeAlterar) href="{{ route('mesas.edit', $mesa) }}" title="Clique para editar" @endif
                    class="group flex gap-4 bg-white rounded-2xl shadow-sm ring-1 {{ $mesa->ativo ? $s['borda'] : 'ring-slate-200 opacity-60' }} p-4 transition {{ $podeAlterar ? 'hover:shadow-md' : '' }}">

                    {{-- Número --}}
                    <div class="shrink-0 w-20 h-20 rounded-xl bg-[#10284A] text-amber-50 flex flex-col items-center justify-center">
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-300">Mesa</span>
                        <span class="text-2xl font-bold leading-none">{{ $mesa->numero }}</span>
                    </div>

                    <div class="min-w-0 flex-1 flex flex-col">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-bold text-slate-800 leading-snug truncate group-hover:text-[#10284A]">{{ $mesa->nome ?: 'Mesa ' . $mesa->numero }}</h3>
                            @unless ($mesa->ativo)
                                <span class="shrink-0 rounded-full bg-red-600 text-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide">Inativa</span>
                            @endunless
                        </div>
                        <p class="text-xs text-slate-400">{{ $mesa->lugares ? $mesa->lugares . ' ' . ($mesa->lugares === 1 ? 'lugar' : 'lugares') : 'Lugares não informados' }}</p>

                        <div class="mt-auto pt-2 flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $s['selo'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $s['ponto'] }}"></span> {{ $s['rotulo'] }}
                            </span>
                            @if ($mesa->status !== 'disponivel' && $mesa->status_desde)
                                <span class="text-xs text-slate-500" title="Desde {{ $mesa->status_desde->format('d/m H:i') }}{{ $mesa->alteradoPor ? ' · ' . $mesa->alteradoPor->name : '' }}"
                                      data-desde="{{ $mesa->status_desde->toIso8601String() }}"></span>
                            @endif
                        </div>
                    </div>
                </{{ $podeAlterar ? 'a' : 'div' }}>
            @endforeach
        </div>
    @endif

    @include('comandas.mesas._relogio')
@endsection
