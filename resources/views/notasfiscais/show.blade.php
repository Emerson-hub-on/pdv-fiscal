@extends('layouts.app')

@section('titulo', 'Nota Fiscal #' . $notaFiscal->id)

@section('conteudo')
<div class="flex flex-col gap-6 max-w-4xl">

    <div class="bg-white rounded-lg shadow p-6 flex justify-between items-start">
        <div>
            <h1 class="text-lg font-semibold">Nota Fiscal {{ $notaFiscal->numero ? '#' . $notaFiscal->numero : '(rascunho)' }}</h1>
            <p class="text-sm text-gray-500">Cliente: {{ $notaFiscal->cliente->nome }}</p>
            <p class="text-sm text-gray-500">Status:
                <span class="font-medium">{{ ucfirst($notaFiscal->status) }}</span>
            </p>
        </div>

        @if ($notaFiscal->status === 'rascunho')
            <div class="flex gap-2">
                <form method="POST" action="{{ route('notasfiscais.recalcular', $notaFiscal) }}"
                    onsubmit="return confirm('Isso vai atualizar NCM, CEST, tributação, PIS/COFINS e IPI de cada item com base no cadastro atual dos produtos. Quantidade, valor e desconto não serão alterados. Confirma?')">
                    @csrf
                    <button type="submit"
                            class="border border-gray-300 rounded-lg px-4 py-2 text-sm font-medium hover:bg-gray-50">
                        Recalcular
                    </button>
                </form>
                <a href="{{ route('notasfiscais.previsualizar', $notaFiscal) }}" target="_blank"
                class="border border-gray-300 rounded-lg px-4 py-2 text-sm font-medium hover:bg-gray-50">
                    Pré-visualizar PDF
                </a>
                <form method="POST" action="{{ route('notasfiscais.emitir', $notaFiscal) }}">
                    @csrf
                    <button type="submit"
                            class="bg-green-700 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-green-800">
                        Emitir NF-e
                    </button>
                </form>
            </div>
        @elseif ($notaFiscal->status === 'emitida')
            <div class="flex gap-2">
                <a href="{{ route('notasfiscais.xml', $notaFiscal) }}"
                   class="border border-gray-300 rounded-lg px-4 py-2 text-sm hover:bg-gray-50">XML</a>
                <a href="{{ route('notasfiscais.cancelar-form', $notaFiscal) }}"
                   class="border border-red-300 text-red-700 rounded-lg px-4 py-2 text-sm hover:bg-red-50">Cancelar</a>
            </div>
        @endif
    </div>

    @error('emissao')
        <div class="bg-red-100 text-red-800 border border-red-300 rounded px-4 py-3">{{ $message }}</div>
    @enderror

    @if ($notaFiscal->status === 'rascunho')
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-sm font-semibold mb-3">Adicionar item</h2>

            <input type="text" id="input-busca-item-nf" placeholder="Nome, código interno ou código de barras..."
                autocomplete="off"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-4 focus:ring-2 focus:ring-slate-800 outline-none transition">

            <form id="form-item" method="POST" action="{{ route('notasfiscais.itens.adicionar', $notaFiscal) }}"
                class="hidden grid grid-cols-4 gap-3 items-end">
                @csrf
                <input type="hidden" name="produto_id" id="item-produto-id">
                <div class="col-span-4 text-sm font-medium" id="item-produto-nome"></div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Quantidade</label>
                    <input type="number" step="0.001" name="quantidade" value="1" required
                        class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Valor unitário</label>
                    <input type="number" step="0.0001" name="valor_unitario" id="item-valor-unitario" required
                        class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">CFOP</label>
                    <input type="text" name="cfop" maxlength="4" placeholder="5102" required
                        class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                </div>
                <button type="submit"
                        class="bg-gray-800 text-white rounded-lg px-3 py-1.5 text-sm h-fit hover:bg-gray-700">
                    Adicionar
                </button>
            </form>
        </div>

        <!-- Modal de busca de produto — mesmo padrão do caixa -->
        <div id="modal-busca-produto-nf" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[80vh] overflow-y-auto">
                <div class="flex justify-between items-center px-6 py-4 bg-linear-to-r from-slate-800 via-slate-900 to-slate-900">
                    <h2 class="text-lg font-bold text-white">Selecionar Produto</h2>
                    <button onclick="fecharModalBuscaNf()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
                </div>

                <div class="p-6">
                    <input type="text" id="busca-produto-modal-nf" placeholder="Buscar por nome, código ou código de barras..."
                        class="w-full border border-gray-200 rounded-lg px-4 py-3 text-sm mb-4 focus:ring-2 focus:ring-slate-800 outline-none transition">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-400 uppercase tracking-wide border-b">
                                <th class="py-2">Código</th>
                                <th class="py-2">Produto</th>
                                <th class="py-2">Preço</th>
                            </tr>
                        </thead>
                        <tbody id="linhas-busca-produto-nf"></tbody>
                    </table>

                    <p class="text-xs text-gray-400 mt-3">Use ↑ ↓ para navegar e Enter para selecionar.</p>
                </div>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="text-left px-4 py-2">Produto</th>
                    <th class="text-right px-4 py-2">Qtd</th>
                    <th class="text-right px-4 py-2">Unit.</th>
                    <th class="text-right px-4 py-2">Total</th>
                    @if ($notaFiscal->status === 'rascunho') <th></th> @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($notaFiscal->itens as $item)
                    <tr>
                        <td class="px-4 py-2">{{ $item->produto->nome }}</td>
                        <td class="px-4 py-2 text-right">{{ $item->quantidade }}</td>
                        <td class="px-4 py-2 text-right">R$ {{ number_format($item->valor_unitario, 2, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right">R$ {{ number_format($item->valor_total, 2, ',', '.') }}</td>
                        @if ($notaFiscal->status === 'rascunho')
                            <td class="px-4 py-2 text-right">
                                <form method="POST" action="{{ route('notasfiscais.itens.remover', [$notaFiscal, $item]) }}">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 text-xs hover:underline">remover</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 font-medium">
                <tr>
                    <td colspan="3" class="px-4 py-2 text-right">Total</td>
                    <td class="px-4 py-2 text-right">R$ {{ number_format($notaFiscal->valor_total, 2, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
let resultadosAtuaisNf = [];
let indiceSelecionadoNf = -1;
let timeoutBuscaNf;

const inputBuscaNf = document.getElementById('input-busca-item-nf');
const inputBuscaModalNf = document.getElementById('busca-produto-modal-nf');
const linhasBuscaDivNf = document.getElementById('linhas-busca-produto-nf');

// Campo principal: digitação abre o modal (exceto enquanto for só números —
// evita o modal "piscando" a cada dígito de um código de barras sendo digitado/escaneado)
inputBuscaNf?.addEventListener('input', () => {
    if (!document.getElementById('modal-busca-produto-nf').classList.contains('hidden')) return;

    clearTimeout(timeoutBuscaNf);
    const valor = inputBuscaNf.value.trim();

    if (/^\d+$/.test(valor)) return; // aguarda o Enter pra códigos puramente numéricos

    if (valor.length < 1) return;

    timeoutBuscaNf = setTimeout(() => buscarProdutoNf(valor), 300);
});

// Enter no campo principal: se for código exato (barras ou interno), adiciona
// direto sem abrir modal. Senão, busca e abre modal com o que encontrar.
inputBuscaNf?.addEventListener('keydown', async (e) => {
    if (e.key !== 'Enter') return;
    if (!document.getElementById('modal-busca-produto-nf').classList.contains('hidden')) return;

    e.preventDefault();
    const termo = inputBuscaNf.value.trim();
    if (termo.length < 1) return;

    const resp = await fetch(`{{ route('notasfiscais.buscar-produto') }}?termo=${encodeURIComponent(termo)}`);
    const produtos = await resp.json();

    const exato = produtos.find(p => p.codigo_barras === termo || p.codigo_interno === termo);

    if (exato) {
        selecionarProdutoNf(exato);
        inputBuscaNf.value = '';
        return;
    }

    resultadosAtuaisNf = produtos;
    indiceSelecionadoNf = produtos.length > 0 ? 0 : -1;
    abrirModalBuscaNf(termo);
});

function abrirModalBuscaNf(termoInicial) {
    const modal = document.getElementById('modal-busca-produto-nf');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    inputBuscaModalNf.value = termoInicial ?? inputBuscaNf.value;
    inputBuscaNf.value = '';
    renderizarResultadosNf();
    inputBuscaModalNf.focus();
}

function fecharModalBuscaNf() {
    document.getElementById('modal-busca-produto-nf').classList.add('hidden');
    document.getElementById('modal-busca-produto-nf').classList.remove('flex');
    inputBuscaModalNf.value = '';
    resultadosAtuaisNf = [];
    indiceSelecionadoNf = -1;
    inputBuscaNf.focus();
}

async function buscarProdutoNf(termo) {
    const resp = await fetch(`{{ route('notasfiscais.buscar-produto') }}?termo=${encodeURIComponent(termo)}`);
    resultadosAtuaisNf = await resp.json();
    indiceSelecionadoNf = resultadosAtuaisNf.length > 0 ? 0 : -1;
    abrirModalBuscaNf(termo);
}

inputBuscaModalNf?.addEventListener('input', () => {
    clearTimeout(timeoutBuscaNf);
    const valor = inputBuscaModalNf.value.trim();
    if (valor.length < 1) { resultadosAtuaisNf = []; renderizarResultadosNf(); return; }
    timeoutBuscaNf = setTimeout(() => buscarProdutoNf(valor), 300);
});

inputBuscaModalNf?.addEventListener('keydown', (e) => {
    if (resultadosAtuaisNf.length === 0) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        indiceSelecionadoNf = (indiceSelecionadoNf + 1) % resultadosAtuaisNf.length;
        renderizarResultadosNf();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        indiceSelecionadoNf = (indiceSelecionadoNf - 1 + resultadosAtuaisNf.length) % resultadosAtuaisNf.length;
        renderizarResultadosNf();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (indiceSelecionadoNf >= 0) selecionarResultadoNf(indiceSelecionadoNf);
    } else if (e.key === 'Escape') {
        fecharModalBuscaNf();
    }
});

function renderizarResultadosNf() {
    if (resultadosAtuaisNf.length === 0) {
        linhasBuscaDivNf.innerHTML = '<tr><td colspan="3" class="p-3 text-sm text-gray-400 text-center">Nenhum produto encontrado.</td></tr>';
        return;
    }

    linhasBuscaDivNf.innerHTML = resultadosAtuaisNf.map((p, index) => {
        const destacado = index === indiceSelecionadoNf;
        const codigo = p.codigo_barras || p.codigo_interno;

        return `
            <tr class="cursor-pointer border-b border-gray-100 transition ${destacado ? 'bg-slate-800 text-white' : 'hover:bg-gray-50'}"
                onclick="selecionarResultadoNf(${index})">
                <td class="py-3 font-mono text-sm ${destacado ? 'text-slate-300' : 'text-gray-500'}">${codigo}</td>
                <td class="py-3 font-medium">${p.nome}</td>
                <td class="py-3 ${destacado ? 'text-emerald-300' : 'text-emerald-600'} font-semibold">R$ ${Number(p.preco_venda).toFixed(2)}</td>
            </tr>
        `;
    }).join('');
}

function selecionarResultadoNf(index) {
    const produto = resultadosAtuaisNf[index];
    fecharModalBuscaNf();
    selecionarProdutoNf(produto);
}

function selecionarProdutoNf(produto) {
    document.getElementById('item-produto-id').value = produto.id;
    document.getElementById('item-produto-nome').innerText = produto.nome;
    document.getElementById('item-valor-unitario').value = produto.preco_venda;
    document.getElementById('form-item').classList.remove('hidden');
}
</script>
@endsection