@extends('layouts.app')

@section('titulo', 'Montagem NF-e de troca')

@section('conteudo')
@php
    extract(config('estilo.formulario'));

    $rotulosStatus = [
        'emitida'      => ['Autorizada', 'bg-green-100 text-green-800'],
        'contingencia' => ['Contingência', 'bg-amber-100 text-amber-800'],
        'cancelada'    => ['Cancelada', 'bg-red-100 text-red-700'],
        'pendente'     => ['Não autorizada', 'bg-slate-100 text-slate-700'],
        'rascunho'     => ['Rascunho', 'bg-slate-100 text-slate-700'],
    ];
@endphp

<div class="flex flex-col gap-4 w-full max-w-6xl mt-2 mb-6">
    <div class="{{ $cartaoCls }}">
        <x-form.cabecalho rotulo="Faturamento · Montagem NF-e" titulo="NF-e de troca" />

        <div class="px-6 pt-5">
            <div class="grid grid-cols-2 gap-3 max-w-3xl text-sm">
                <div class="flex gap-3 rounded-xl bg-slate-50 ring-1 ring-slate-200 px-4 py-3">
                    <span class="shrink-0 w-7 h-7 rounded-lg bg-[#10284A] text-amber-50 flex items-center justify-center text-xs font-bold">1</span>
                    <div>
                        <p class="font-semibold text-slate-800">Entrada do item devolvido</p>
                        <p class="text-xs text-slate-500">NF-e de entrada (devolução de cliente) referenciando a venda original — CFOP 1202/2202 ou 1411/2411 (ST).</p>
                    </div>
                </div>
                <div class="flex gap-3 rounded-xl bg-slate-50 ring-1 ring-slate-200 px-4 py-3">
                    <span class="shrink-0 w-7 h-7 rounded-lg bg-[#10284A] text-amber-50 flex items-center justify-center text-xs font-bold">2</span>
                    <div>
                        <p class="font-semibold text-slate-800">Saída do item novo</p>
                        <p class="text-xs text-slate-500">Venda normal no caixa (NFC-e) ou NF-e de venda (CFOP 5102 / 5405), aberta a partir da entrada.</p>
                    </div>
                </div>
            </div>
        </div>

        <form method="GET" action="{{ route('montagem-nfe.troca.index') }}" class="p-6 flex flex-wrap items-end gap-4">
            <input type="hidden" name="doc" value="{{ $documento }}">
            <input type="hidden" name="modo" value="{{ $modo }}">

            <div>
                <span class="{{ $labelCls }}">Venda original</span>
                <div class="flex rounded-xl ring-1 ring-slate-200 overflow-hidden text-sm">
                    @foreach (['nfce' => 'Cupom (NFC-e)', 'nfe' => 'NF-e de venda'] as $chave => $nome)
                        <a href="{{ route('montagem-nfe.troca.index', ['doc' => $chave, 'modo' => $modo]) }}"
                           class="px-4 py-2 font-medium transition {{ $loop->first ? '' : 'border-l border-slate-200' }} {{ $documento === $chave ? 'bg-[#10284A] text-amber-50' : 'bg-white text-slate-600 hover:bg-slate-50' }}">
                            {{ $nome }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div>
                <span class="{{ $labelCls }}">Buscar por</span>
                <div class="flex rounded-xl ring-1 ring-slate-200 overflow-hidden text-sm">
                    @foreach (['numero' => $documento === 'nfce' ? 'Série e número' : 'Número', 'data' => 'Data'] as $chave => $nome)
                        <a href="{{ route('montagem-nfe.troca.index', ['doc' => $documento, 'modo' => $chave]) }}"
                           class="px-4 py-2 font-medium transition {{ $loop->first ? '' : 'border-l border-slate-200' }} {{ $modo === $chave ? 'bg-[#10284A] text-amber-50' : 'bg-white text-slate-600 hover:bg-slate-50' }}">
                            {{ $nome }}
                        </a>
                    @endforeach
                </div>
            </div>

            @if ($modo === 'numero')
                @if ($documento === 'nfce')
                    <div class="w-24">
                        <label for="campo-serie" class="{{ $labelCls }}">Série</label>
                        <input type="number" name="serie" id="campo-serie" min="0" max="999" required
                               value="{{ request('serie', $seriePadrao) }}" class="{{ $inputCls }}">
                    </div>
                @endif
                <div class="w-44">
                    <label for="campo-numero" class="{{ $labelCls }}">{{ $documento === 'nfce' ? 'Número do cupom' : 'Número da NF-e' }}</label>
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

    @if ($mensagem)
        <div class="flex items-start gap-3 rounded-xl px-4 py-3 text-sm {{ $mensagemSincronizacao ? 'bg-amber-50 ring-1 ring-amber-300 text-amber-900' : 'bg-red-50 ring-1 ring-red-200 text-red-800' }}">
            <x-form.icone nome="info" class="mt-0.5 shrink-0" />
            <div>
                @if ($mensagemSincronizacao)
                    <p class="font-semibold">Venda não sincronizada</p>
                @endif
                <p>{{ $mensagem }}</p>
            </div>
        </div>
    @endif

    @if ($pendentes->isNotEmpty())
        <div class="flex items-start gap-3 bg-amber-50 ring-1 ring-amber-300 rounded-xl px-4 py-3 text-sm text-amber-900">
            <x-form.icone nome="info" class="mt-0.5 shrink-0" />
            <div>
                <p class="font-semibold">Venda não sincronizada</p>
                <p>{{ $pendentes->count() === 1 ? 'Há 1 venda deste dia' : "Há {$pendentes->count()} vendas deste dia" }} ainda só no caixa. Use "Atualizar Caixa" para enviá-las ao servidor antes de fazer a troca.</p>
            </div>
        </div>
    @endif

    @if ($modo === 'data' || $linhas->isNotEmpty())
        <div class="{{ $cartaoCls }} overflow-hidden">
            <div class="overflow-auto max-h-[calc(100vh-22rem)]">
                <table class="w-full text-sm text-slate-700">
                    <thead class="{{ $theadCls }} sticky top-0 z-10">
                        <tr>
                            <th class="text-left px-4 py-2.5">Documento</th>
                            <th class="text-left px-4 py-2.5">Data</th>
                            <th class="text-left px-4 py-2.5">Cliente</th>
                            <th class="text-right px-4 py-2.5">Total</th>
                            <th class="text-left px-4 py-2.5">Situação / trocas</th>
                            <th class="text-right px-4 py-2.5">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($linhas as $linha)
                            @php
                                $m = $linha['doc']['modelo'];
                                $ehNfe = $linha['doc']['tipo'] === 'nfe';
                                [$rotulo, $cor] = $rotulosStatus[$m->status] ?? [ucfirst((string) $m->status), 'bg-slate-100 text-slate-700'];
                                $documentoCliente = $m->cliente?->cpf_cnpj_formatado ?? ($ehNfe ? null : $m->cpf_na_nota);
                            @endphp
                            <tr class="hover:bg-slate-50 align-top">
                                <td class="px-4 py-2.5">
                                    <span class="font-semibold font-mono">{{ $ehNfe ? $m->numero : $m->numero_nfce }}</span>
                                    <span class="text-slate-400">/ {{ $ehNfe ? $m->serie : $m->serie_nfce }} · {{ $ehNfe ? 'NF-e' : 'NFC-e' }}</span>
                                </td>
                                <td class="px-4 py-2.5">{{ ($ehNfe ? $m->emitida_em : $m->created_at)?->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-2.5">
                                    @if ($m->cliente)
                                        {{ $m->cliente->nome }} <span class="text-slate-400">{{ $documentoCliente }}</span>
                                    @elseif ($documentoCliente)
                                        <span class="text-slate-500">CPF/CNPJ {{ $documentoCliente }}</span>
                                    @else
                                        <span class="text-slate-400">Consumidor não identificado</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right font-medium">R$ {{ number_format((float) ($ehNfe ? $m->valor_total : $m->total), 2, ',', '.') }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $cor }}">{{ $rotulo }}</span>
                                    @foreach ($linha['trocas'] as $entrada)
                                        @php $vendaNova = ($linha['vendas'][$entrada->id] ?? collect())->first(); @endphp
                                        <div class="text-xs mt-1">
                                            <a href="{{ $entrada->status === 'rascunho' ? route('notasfiscais.edit', $entrada) : route('notasfiscais.show', $entrada) }}"
                                               class="text-[#10284A] hover:underline">Entrada {{ $entrada->numero ? 'nº ' . $entrada->numero : '(rascunho #' . $entrada->id . ')' }}</a>
                                            <span class="text-slate-400">→</span>
                                            @if ($vendaNova)
                                                <a href="{{ $vendaNova->status === 'rascunho' ? route('notasfiscais.edit', $vendaNova) : route('notasfiscais.show', $vendaNova) }}"
                                                   class="text-[#10284A] hover:underline">Venda {{ $vendaNova->numero ? 'nº ' . $vendaNova->numero : '(rascunho)' }}</a>
                                            @else
                                                <a href="{{ route('montagem-nfe.troca.venda', $entrada) }}" class="text-[#10284A] hover:underline">montar NF-e de venda</a>
                                                <span class="text-slate-400">ou venda no caixa</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                    @if ($linha['bloqueio'])
                                        <span class="text-xs text-slate-400" title="{{ $linha['bloqueio'] }}">Indisponível</span>
                                    @else
                                        <button type="button" onclick="abrirItens('{{ $linha['doc']['tipo'] }}', {{ $m->id }})" class="text-xs {{ $botaoAcaoCls }} py-1.5!">
                                            <x-form.icone nome="documento" tamanho="w-3.5 h-3.5" /> Fazer troca
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @if ($linha['bloqueio'] && $modo === 'numero')
                                <tr>
                                    <td colspan="6" class="px-4 pb-3">
                                        <div class="flex items-start gap-2 text-xs text-red-700">
                                            <x-form.icone nome="info" tamanho="w-3.5 h-3.5" class="mt-0.5 shrink-0" />
                                            <span>{{ $linha['bloqueio'] }}</span>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-slate-400">
                                    Nenhuma {{ $documento === 'nfce' ? 'NFC-e' : 'NF-e de venda' }} em {{ \Illuminate\Support\Carbon::parse($data)->format('d/m/Y') }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif (!$mensagem)
        <p class="px-4 py-8 text-center text-sm text-slate-400">
            {{ $documento === 'nfce' ? 'Informe a série e o número do cupom (NFC-e) da venda original e clique em Buscar.' : 'Informe o número da NF-e de venda original e clique em Buscar.' }}
        </p>
    @endif
</div>

{{-- Janela: itens que o cliente está devolvendo --}}
<div id="modal-itens" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col">
        <div class="flex justify-between items-center gap-4 px-6 py-4 bg-[#10284A] rounded-t-xl">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Troca · itens devolvidos pelo cliente</p>
                <h2 id="modal-titulo" class="text-lg font-semibold text-amber-50 truncate">Venda original</h2>
            </div>
            <button type="button" onclick="fecharItens()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>

        <div class="px-6 pt-4 pb-2 flex flex-wrap items-center justify-between gap-3">
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 cursor-pointer">
                <input type="checkbox" id="marcar-todos" class="w-4 h-4 rounded accent-[#10284A]" onchange="marcarTodos(this.checked)">
                Selecionar todos
            </label>
            <p class="text-xs text-slate-500">Marque os itens que voltaram e ajuste a quantidade, se for parcial.</p>
        </div>

        <div class="px-6 overflow-auto flex-1">
            <div class="{{ $tabelaCaixaCls }}">
                <table class="w-full text-sm text-slate-700">
                    <thead class="{{ $theadCls }} sticky top-0 z-10">
                        <tr>
                            <th class="px-3 py-2.5 w-10"></th>
                            <th class="text-left px-3 py-2.5">Item</th>
                            <th class="text-left px-3 py-2.5">Descrição</th>
                            <th class="text-right px-3 py-2.5">Vendido</th>
                            <th class="text-right px-3 py-2.5">Já devolvido</th>
                            <th class="text-right px-3 py-2.5 w-36">Devolver</th>
                            <th class="text-right px-3 py-2.5">Valor</th>
                        </tr>
                    </thead>
                    <tbody id="linhas-itens" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 mt-2 border-t border-slate-100">
            <p class="text-sm text-slate-600">
                <span id="resumo-qtd">0 itens</span> ·
                Valor devolvido <span id="resumo-total" class="text-base font-bold text-[#10284A]">R$ 0,00</span>
            </p>
            <div class="flex gap-3">
                <button type="button" onclick="fecharItens()" class="{{ $botaoCancelarCls }}">Cancelar</button>
                <button type="button" id="btn-montar" onclick="montar()" class="{{ $botaoPrimarioCls }}" disabled>
                    <x-form.icone nome="check" /> Montar NF-e de entrada
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const urlItensModelo  = @json(route('montagem-nfe.troca.itens', ['tipo' => '__TIPO__', 'id' => 0]));
const urlMontarModelo = @json(route('montagem-nfe.troca.montar', ['tipo' => '__TIPO__', 'id' => 0]));
const clsQtd = 'w-28 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 text-sm text-right focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition disabled:opacity-50';

let docAtual = null;
let itens = [];

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const moeda = (v) => (Number(v) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const qtdBr = (v) => (Number(v) || 0).toLocaleString('pt-BR', { maximumFractionDigits: 3 });
const urlDoc = (modelo, tipo, id) => modelo.replace('__TIPO__', tipo).replace(/\/0\/(itens|montar)$/, `/${id}/$1`);

async function abrirItens(tipo, id) {
    const modal = document.getElementById('modal-itens');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.getElementById('marcar-todos').checked = false;
    document.getElementById('linhas-itens').innerHTML = '<tr><td colspan="7" class="px-3 py-8 text-center text-slate-400">Carregando itens…</td></tr>';

    try {
        const resp = await fetch(urlDoc(urlItensModelo, tipo, id), { headers: { 'Accept': 'application/json' } });
        const dados = await resp.json();
        if (!resp.ok) throw new Error(dados.message ?? `Erro ${resp.status} ao carregar os itens.`);

        docAtual = dados.documento;
        itens = dados.itens.map((i) => ({ ...i, marcado: false, devolver: i.saldo }));
        document.getElementById('modal-titulo').textContent =
            `${dados.documento.rotulo}${dados.documento.cliente ? ' — ' + dados.documento.cliente : ' — consumidor não identificado'}`;
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
    }).join('') || '<tr><td colspan="7" class="px-3 py-8 text-center text-slate-400">O documento não tem itens.</td></tr>';

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
    const url = new URL(urlDoc(urlMontarModelo, docAtual.tipo, docAtual.id));
    itens.filter((i) => i.marcado && i.devolver > 0).forEach((i) => url.searchParams.set(`q[${i.id}]`, i.devolver));
    window.location = url.toString();
}

document.addEventListener('keydown', (e) => { if (e.key === 'Escape') fecharItens(); });
(document.getElementById('campo-serie')?.value ? document.getElementById('campo-numero') : (document.getElementById('campo-serie') ?? document.getElementById('campo-numero')))?.focus();
</script>
@endsection
