@extends('layouts.app')

@section('titulo', 'Cardápio')

@section('conteudo')
@php
    extract(config('estilo.formulario'));
    $podeAlterar = auth()->user()?->podeAlterar('comandas');
    $filtrando = $busca !== '' || $categoriaId || $situacao !== 'todos';
@endphp

    <x-form.cabecalho solto rotulo="Comandas · Cardápio" titulo="Menu">
        @if ($podeAlterar)
            <a href="{{ route('cardapio.create') }}" class="{{ $botaoAcaoCls }} bg-amber-50! text-[#10284A]! hover:bg-white!">
                <x-form.icone nome="mais" /> Adicionar produto
            </a>
        @endif
    </x-form.cabecalho>

    <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900 mb-5">
        Estes são os produtos que o <strong>garçom</strong> vê ao fazer o pedido, na ordem abaixo. Nome, valor e estoque vêm do
        cadastro do produto; aqui ficam a <strong>foto</strong>, a descrição do cardápio e se o item aparece. Itens inativos não aparecem para o garçom.
        @if ($podeAlterar) Clique num item para trocar a foto, editar ou retirar do cardápio. @endif
    </div>

    {{-- Cartões de resumo --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        @foreach ([
            ['Itens no cardápio', $totais['itens'], 'text-slate-800'],
            ['Visíveis ao garçom', $totais['ativos'], 'text-emerald-700'],
            ['Sem foto', $totais['sem_foto'], $totais['sem_foto'] ? 'text-amber-700' : 'text-slate-800'],
            ['Sem estoque', $totais['sem_estoque'], $totais['sem_estoque'] ? 'text-red-600' : 'text-slate-800'],
        ] as [$rotulo, $valor, $cor])
            <div class="{{ $cartaoCls }} px-4 py-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $rotulo }}</p>
                <p class="text-2xl font-bold {{ $cor }}">{{ number_format($valor, 0, ',', '.') }}</p>
            </div>
        @endforeach
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('cardapio.index') }}" class="{{ $cartaoCls }} p-4 mb-5 grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
        <div class="sm:col-span-6">
            <label class="{{ $labelCls }}">Buscar</label>
            <input type="search" name="busca" value="{{ $busca }}" placeholder="Nome, código interno ou código de barras" class="{{ $inputCls }}">
        </div>
        <div class="sm:col-span-3">
            <label class="{{ $labelCls }}">Categoria</label>
            <select name="categoria" class="{{ $inputCls }}" onchange="this.form.submit()">
                <option value="">Todas</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id }}" @selected($categoriaId === $categoria->id)>{{ $categoria->nome }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="{{ $labelCls }}">Situação</label>
            <select name="situacao" class="{{ $inputCls }}" onchange="this.form.submit()">
                <option value="todos" @selected($situacao === 'todos')>Todos</option>
                <option value="ativos" @selected($situacao === 'ativos')>Ativos</option>
                <option value="inativos" @selected($situacao === 'inativos')>Inativos</option>
            </select>
        </div>
        <div class="sm:col-span-1 flex gap-2">
            <button type="submit" class="{{ $botaoAcaoCls }} w-full">Filtrar</button>
        </div>
        @if ($filtrando)
            <div class="sm:col-span-12 -mt-1">
                <a href="{{ route('cardapio.index') }}" class="text-xs text-slate-500 hover:text-[#10284A] underline">Limpar filtros</a>
            </div>
        @endif
    </form>

    {{-- Cards do cardápio: como o garçom vai ver cada produto --}}
    @if ($itens->isEmpty())
        <div class="{{ $cartaoCls }} px-6 py-14 text-center">
            <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <p class="text-slate-500 text-sm">
                {{ $filtrando ? 'Nenhum item encontrado com esses filtros.' : 'O cardápio ainda está vazio.' }}
            </p>
            @if (!$filtrando && $podeAlterar)
                <a href="{{ route('cardapio.create') }}" class="{{ $botaoAcaoCls }} mt-4">
                    <x-form.icone nome="mais" /> Adicionar o primeiro produto
                </a>
            @endif
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($itens as $item)
                @php
                    $produto = $item->produto;
                    $estoque = $item->estoqueAtual();
                    $semEstoque = $estoque <= 0;
                    $estoqueBaixo = !$semEstoque && $produto->estoque_minimo > 0 && $estoque <= $produto->estoque_minimo;
                    $descricao = $item->descricaoExibida();
                @endphp
                <{{ $podeAlterar ? 'a' : 'div' }} @if ($podeAlterar) href="{{ route('cardapio.edit', $item) }}" title="Clique para editar" @endif
                    class="group {{ $cartaoCls }} overflow-hidden flex flex-col transition {{ $podeAlterar ? 'hover:ring-[#10284A]/40 hover:shadow-md' : '' }} {{ $item->ativo ? '' : 'opacity-60' }}">

                    {{-- Foto --}}
                    <div class="relative aspect-4/3 bg-slate-100">
                        @if ($item->imagem)
                            <img src="{{ $item->urlImagem() }}" alt="{{ $item->nomeExibido() }}" loading="lazy" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex flex-col items-center justify-center text-slate-400">
                                <svg class="w-10 h-10 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span class="text-xs">Sem foto</span>
                            </div>
                        @endif
                        <span class="absolute top-2 left-2 rounded-md bg-[#10284A]/85 text-amber-50 px-2 py-0.5 text-[11px] font-mono">#{{ $item->ordem }}</span>
                        @unless ($item->ativo)
                            <span class="absolute top-2 right-2 rounded-full bg-red-600 text-white px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide">Inativo</span>
                        @endunless
                    </div>

                    {{-- Dados --}}
                    <div class="p-4 flex flex-col gap-2 flex-1">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="font-semibold text-slate-800 leading-snug group-hover:text-[#10284A]">{{ $item->nomeExibido() }}</h3>
                                <p class="text-xs text-slate-400">
                                    <span class="font-mono">{{ $item->codigoExibido() ?: 'Sem código' }}</span>
                                    @if ($produto->categoria) · {{ $produto->categoria->nome }} @endif
                                </p>
                            </div>
                            <p class="shrink-0 text-lg font-bold text-[#10284A] whitespace-nowrap">R$ {{ number_format((float) $produto->preco_venda, 2, ',', '.') }}</p>
                        </div>

                        <p class="text-sm text-slate-500 line-clamp-3 {{ $descricao ? '' : 'italic text-slate-400' }}">
                            {{ $descricao ?: 'Sem descrição.' }}
                        </p>

                        <div class="mt-auto pt-2 flex items-center justify-between border-t border-slate-100">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Estoque</span>
                            <span @class([
                                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium',
                                'bg-red-50 text-red-600' => $semEstoque,
                                'bg-amber-50 text-amber-700' => $estoqueBaixo,
                                'bg-green-50 text-green-700' => !$semEstoque && !$estoqueBaixo,
                            ])>
                                {{ rtrim(rtrim(number_format($estoque, 3, ',', '.'), '0'), ',') }} {{ $produto->unidade_comercial ?: 'UN' }}
                                @if ($semEstoque) · sem estoque @elseif ($estoqueBaixo) · baixo @endif
                            </span>
                        </div>                    </div>
                </{{ $podeAlterar ? 'a' : 'div' }}>
            @endforeach
        </div>
    @endif
@endsection
