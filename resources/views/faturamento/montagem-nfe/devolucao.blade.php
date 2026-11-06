@extends('layouts.app')

@section('titulo', 'Montagem NF-e de devolução')

@section('conteudo')
@php
    extract(config('estilo.formulario'));

    $rotulosStatus = [
        'finalizada' => ['Finalizada', 'bg-green-100 text-green-800'],
        'rascunho'   => ['Rascunho', 'bg-amber-100 text-amber-800'],
    ];
@endphp

<div class="flex flex-col gap-4 w-full max-w-6xl mt-2 mb-6">
    <div class="{{ $cartaoCls }}">
        <x-form.cabecalho rotulo="Faturamento · Montagem NF-e" titulo="NF-e de devolução" />

        <form method="GET" action="{{ route('montagem-nfe.devolucao.index') }}" class="p-6 flex flex-wrap items-end gap-4">
            <input type="hidden" name="modo" value="{{ $modo }}">

            <div>
                <span class="{{ $labelCls }}">Buscar por</span>
                <div class="flex rounded-xl ring-1 ring-slate-200 overflow-hidden text-sm">
                    <button type="button" onclick="trocarModo('data')"
                            class="px-4 py-2 font-medium transition {{ $modo === 'data' ? 'bg-[#10284A] text-amber-50' : 'bg-white text-slate-600 hover:bg-slate-50' }}">
                        Data de entrada
                    </button>
                    <button type="button" onclick="trocarModo('numero')"
                            class="px-4 py-2 font-medium border-l border-slate-200 transition {{ $modo === 'numero' ? 'bg-[#10284A] text-amber-50' : 'bg-white text-slate-600 hover:bg-slate-50' }}">
                        Número da nota
                    </button>
                </div>
            </div>

            @if ($modo === 'numero')
                <div class="w-48">
                    <label for="campo-numero" class="{{ $labelCls }}">Número da NF-e de compra</label>
                    <input type="number" name="numero" id="campo-numero" min="1" required
                           value="{{ request('numero') }}" class="{{ $inputCls }}">
                </div>
                <button type="submit" class="{{ $botaoAcaoCls }}">
                    <x-form.icone nome="busca" /> Buscar
                </button>
            @else
                <div>
                    <label for="campo-data" class="{{ $labelCls }}">Data de entrada</label>
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

    @if ($modo === 'data' || $entradas->isNotEmpty())
        <div class="{{ $cartaoCls }} overflow-hidden">
            <div class="overflow-auto max-h-[calc(100vh-18rem)]">
                <table class="w-full text-sm text-slate-700">
                    <thead class="{{ $theadCls }} sticky top-0 z-10">
                        <tr>
                            <th class="text-left px-4 py-2.5">NF-e</th>
                            <th class="text-left px-4 py-2.5">Fornecedor</th>
                            <th class="text-left px-4 py-2.5">Emissão</th>
                            <th class="text-left px-4 py-2.5">Entrada</th>
                            <th class="text-right px-4 py-2.5">Valor</th>
                            <th class="text-left px-4 py-2.5">Situação</th>
                            <th class="text-right px-4 py-2.5">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($entradas as $entrada)
                            @php
                                [$rotulo, $cor] = $rotulosStatus[$entrada->status] ?? [ucfirst((string) $entrada->status), 'bg-slate-100 text-slate-700'];
                                $bloqueio = $bloqueios[$entrada->id] ?? null;
                                $notasDevolucao = $devolucoes[$entrada->id] ?? collect();
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2.5">
                                    <span class="font-semibold font-mono">{{ $entrada->numero }}</span>
                                    <span class="text-slate-400">/ {{ $entrada->serie }}</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    {{ $entrada->fornecedor?->nome ?? '—' }}
                                    @if ($entrada->fornecedor?->uf)
                                        <span class="text-slate-400">{{ $entrada->fornecedor->uf }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">{{ $entrada->data_emissao?->format('d/m/Y') }}</td>
                                <td class="px-4 py-2.5">{{ $entrada->data_entrada?->format('d/m/Y') }}</td>
                                <td class="px-4 py-2.5 text-right font-medium">R$ {{ number_format((float) $entrada->valor_total, 2, ',', '.') }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $cor }}">{{ $rotulo }}</span>
                                    @foreach ($notasDevolucao as $nfe)
                                        <a href="{{ $nfe->status === 'rascunho' ? route('notasfiscais.edit', $nfe) : route('notasfiscais.show', $nfe) }}"
                                           class="block text-xs text-[#10284A] hover:underline mt-0.5">
                                            Devolução {{ $nfe->numero ? 'NF-e nº ' . $nfe->numero : '(rascunho #' . $nfe->id . ')' }}
                                        </a>
                                    @endforeach
                                </td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                    @if ($bloqueio)
                                        <span class="text-xs text-slate-400" title="{{ $bloqueio }}">Indisponível</span>
                                    @else
                                        <button type="button" onclick="abrirItens({{ $entrada->id }})" class="text-xs {{ $botaoAcaoCls }} py-1.5!">
                                            <x-form.icone nome="documento" tamanho="w-3.5 h-3.5" /> Montar devolução
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @if ($bloqueio && $modo === 'numero')
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
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-slate-400">
                                    Nenhuma nota de entrada em {{ \Illuminate\Support\Carbon::parse($data)->format('d/m/Y') }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif (!$naoEncontrado)
        <p class="px-4 py-8 text-center text-sm text-slate-400">Informe o número da NF-e de compra e clique em Buscar.</p>
    @endif
</div>

{{-- Janela: escolha dos itens a devolver --}}
<div id="modal-itens" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col">
        <div class="flex justify-between items-center gap-4 px-6 py-4 bg-[#10284A] rounded-t-xl">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Itens a devolver</p>
                <h2 id="modal-titulo" class="text-lg font-semibold text-amber-50 truncate">Nota de entrada</h2>
            </div>
            <button type="button" onclick="fecharItens()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>

        <div class="px-6 pt-4 pb-2 flex flex-wrap items-center justify-between gap-3">
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 cursor-pointer">
                <input type="checkbox" id="marcar-todos" class="w-4 h-4 rounded accent-[#10284A]" onchange="marcarTodos(this.checked)">
                Selecionar todos
            </label>
            <p class="text-xs text-slate-500">Marque os itens e ajuste a quantidade, se a devolução for parcial.</p>
        </div>

        <div class="px-6 overflow-auto flex-1">
            <div class="{{ $tabelaCaixaCls }}">
                <table class="w-full text-sm text-slate-700">
                    <thead class="{{ $theadCls }} sticky top-0 z-10">
                        <tr>
                            <th class="px-3 py-2.5 w-10"></th>
                            <th class="text-left px-3 py-2.5">Item</th>
                            <th class="text-left px-3 py-2.5">Descrição</th>
                            <th class="text-right px-3 py-2.5">Comprado</th>
                            <th class="text-right px-3 py-2.5">Já devolvido</th>
                            <th class="text-right px-3 py-2.5 w-36">Devolver</th>
                            <th class="text-right px-3 py-2.5">Valor</th>
                        </tr>
                    </thead>
                    <tbody id="linhas-itens" class="divide-y divide-slate-100">
                        <tr><td colspan="7" class="px-3 py-8 text-center text-slate-400">Carregando itens…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 mt-2 border-t border-slate-100">
            <p class="text-sm text-slate-600">
                <span id="resumo-qtd">0 itens</span> ·
                Total a devolver <span id="resumo-total" class="text-base font-bold text-[#10284A]">R$ 0,00</span>
            </p>
            <div class="flex gap-3">
                <button type="button" onclick="fecharItens()" class="{{ $botaoCancelarCls }}">Cancelar</button>
                <button type="button" id="btn-montar" onclick="montar()" class="{{ $botaoPrimarioCls }}" disabled>
                    <x-form.icone nome="check" /> Montar NF-e de devolução
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const urlItensModelo  = @json(route('montagem-nfe.devolucao.itens', ['entrada' => '__ID__']));
const urlMontarModelo = @json(route('montagem-nfe.devolucao.montar', ['entrada' => '__ID__']));
const clsQtd = 'w-28 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 text-sm text-right focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition disabled:opacity-50';

let entradaAtual = null;
let itens = [];

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const moeda = (v) => (Number(v) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const qtdBr = (v) => (Number(v) || 0).toLocaleString('pt-BR', { maximumFractionDigits: 3 });

function trocarModo(modo) {
    const url = new URL(@json(route('montagem-nfe.devolucao.index')));
    url.searchParams.set('modo', modo);
    window.location = url.toString();
}

async function abrirItens(id) {
    const modal = document.getElementById('modal-itens');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.getElementById('marcar-todos').checked = false;
    document.getElementById('linhas-itens').innerHTML = '<tr><td colspan="7" class="px-3 py-8 text-center text-slate-400">Carregando itens…</td></tr>';

    try {
        const resp = await fetch(urlItensModelo.replace('__ID__', id), { headers: { 'Accept': 'application/json' } });
        const dados = await resp.json();
        if (!resp.ok) throw new Error(dados.message ?? `Erro ${resp.status} ao carregar os itens.`);

        entradaAtual = dados.entrada;
        itens = dados.itens.map((i) => ({ ...i, marcado: false, devolver: i.saldo }));
        document.getElementById('modal-titulo').textContent =
            `NF-e nº ${dados.entrada.numero} / ${dados.entrada.serie} — ${dados.entrada.fornecedor ?? ''}`;
        renderizar();
    } catch (e) {
        document.getElementById('linhas-itens').innerHTML = `<tr><td colspan="7" class="px-3 py-8 text-center text-red-700">${esc(e.message)}</td></tr>`;
    }
}

function fecharItens() {
    const modal = document.getElementById('modal-itens');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function renderizar() {
    document.getElementById('linhas-itens').innerHTML = itens.map((i, k) => {
        const semSaldo = i.saldo <= 0;
        return `
            <tr class="${semSaldo ? 'opacity-50' : (i.marcado ? 'bg-amber-50' : 'hover:bg-slate-50')}">
                <td class="px-3 py-2 text-center">
                    <input type="checkbox" class="w-4 h-4 rounded accent-[#10284A]" ${i.marcado ? 'checked' : ''} ${semSaldo ? 'disabled' : ''}
                           onchange="itens[${k}].marcado = this.checked; renderizar()">
                </td>
                <td class="px-3 py-2 font-mono text-slate-500">${i.n_item}</td>
                <td class="px-3 py-2">
                    ${esc(i.descricao)}
                    ${i.com_st ? '<span class="ml-1 rounded-full bg-slate-100 text-slate-600 px-1.5 py-0.5 text-[10px] font-semibold">ST</span>' : ''}
                </td>
                <td class="px-3 py-2 text-right">${qtdBr(i.quantidade)} ${esc(i.unidade ?? '')}</td>
                <td class="px-3 py-2 text-right ${i.devolvido > 0 ? 'text-amber-700' : 'text-slate-400'}">${qtdBr(i.devolvido)}</td>
                <td class="px-3 py-2 text-right">
                    ${semSaldo ? '<span class="text-xs text-slate-400">Sem saldo</span>' : `
                    <input type="number" min="0.001" step="0.001" max="${i.saldo}" value="${i.devolver}" class="${clsQtd}" ${i.marcado ? '' : 'disabled'}
                           oninput="itens[${k}].devolver = Math.min(Number(this.value) || 0, ${i.saldo}); atualizarResumo()">`}
                </td>
                <td class="px-3 py-2 text-right font-medium">${moeda(i.marcado ? i.devolver * i.valor : 0)}</td>
            </tr>`;
    }).join('') || '<tr><td colspan="7" class="px-3 py-8 text-center text-slate-400">A nota não tem itens.</td></tr>';

    const comSaldo = itens.filter((i) => i.saldo > 0);
    document.getElementById('marcar-todos').checked = comSaldo.length > 0 && comSaldo.every((i) => i.marcado);
    atualizarResumo();
}

function marcarTodos(marcar) {
    itens.forEach((i) => { if (i.saldo > 0) i.marcado = marcar; });
    renderizar();
}

function atualizarResumo() {
    const escolhidos = itens.filter((i) => i.marcado && i.devolver > 0);
    const total = escolhidos.reduce((s, i) => s + i.devolver * i.valor, 0);
    document.getElementById('resumo-qtd').textContent = `${escolhidos.length} ${escolhidos.length === 1 ? 'item' : 'itens'}`;
    document.getElementById('resumo-total').textContent = moeda(total);
    document.getElementById('btn-montar').disabled = escolhidos.length === 0;
}

function montar() {
    const url = new URL(urlMontarModelo.replace('__ID__', entradaAtual.id));
    itens.filter((i) => i.marcado && i.devolver > 0).forEach((i) => url.searchParams.set(`q[${i.id}]`, i.devolver));
    window.location = url.toString();
}

document.addEventListener('keydown', (e) => { if (e.key === 'Escape') fecharItens(); });
document.getElementById('campo-numero')?.focus();
</script>
@endsection
