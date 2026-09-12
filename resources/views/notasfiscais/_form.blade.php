@php
    $ehEdicao = isset($notaFiscal) && $notaFiscal !== null;
    $itensIniciais = $ehEdicao
        ? $notaFiscal->itens->map(fn ($i) => [
            'produto_id'     => $i->produto_id,
            'nome'           => $i->produto->nome,
            'cfop'           => $i->cfop,
            'quantidade'     => (float) $i->quantidade,
            'valor_unitario' => (float) $i->valor_unitario,
            'valor_desconto' => (float) $i->valor_desconto,
        ])->values()
        : collect();
@endphp

<form id="form-nota" method="POST"
      action="{{ $ehEdicao ? route('notasfiscais.update', $notaFiscal) : route('notasfiscais.store') }}"
      class="flex flex-col gap-6 max-w-5xl">
    @csrf
    @if ($ehEdicao) @method('PUT') @endif
    <input type="hidden" name="itens_json" id="itens_json">

    <div class="bg-white rounded-lg shadow p-6">
        <h1 class="text-lg font-semibold mb-4">{{ $ehEdicao ? 'Editar Nota Fiscal (rascunho)' : 'Nova Nota Fiscal (Saída)' }}</h1>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                <select name="cliente_id" id="campo-cliente" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Selecione...</option>
                    @foreach ($clientes as $cliente)
                        <option value="{{ $cliente->id }}"
                            @selected(old('cliente_id', $ehEdicao ? $notaFiscal->cliente_id : null) == $cliente->id)>
                            {{ $cliente->nome }} — {{ $cliente->cpf_cnpj_formatado }}
                        </option>
                    @endforeach
                </select>
                @error('cliente_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Natureza da operação</label>
                <input type="text" name="natureza_operacao" id="campo-natureza" required
                       value="{{ old('natureza_operacao', $ehEdicao ? $notaFiscal->natureza_operacao : 'Venda de mercadoria') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Finalidade</label>
                <select name="finalidade" id="campo-finalidade" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach ([1 => 'Normal', 2 => 'Complementar', 3 => 'Ajuste', 4 => 'Devolução'] as $valor => $texto)
                        <option value="{{ $valor }}"
                            @selected(old('finalidade', $ehEdicao ? $notaFiscal->finalidade : 1) == $valor)>
                            {{ $texto }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-sm font-semibold mb-3">Itens da nota</h2>

        <div id="aviso-cabecalho" class="bg-amber-50 text-amber-800 border border-amber-200 rounded-lg px-3 py-2 text-sm mb-4">
            Preencha cliente, natureza da operação e finalidade acima para liberar a adição de itens.
        </div>

        <input type="text" id="input-busca-item-nf" placeholder="Nome, código interno ou código de barras..."
               autocomplete="off" disabled
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-4 focus:ring-2 focus:ring-slate-800 outline-none transition disabled:bg-gray-100 disabled:cursor-not-allowed">

        <div id="editor-item" class="hidden grid grid-cols-5 gap-3 items-end mb-4 bg-gray-50 rounded-lg p-3">
            <div class="col-span-5 text-sm font-medium" id="editor-produto-nome"></div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Quantidade</label>
                <input type="number" step="0.001" id="editor-quantidade" value="1"
                       class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Valor unitário</label>
                <input type="number" step="0.0001" id="editor-valor-unitario"
                       class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Desconto</label>
                <input type="number" step="0.01" id="editor-desconto" value="0"
                       class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">CFOP</label>
                <input type="text" id="editor-cfop" maxlength="4" placeholder="5102"
                       class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
            </div>
            <button type="button" onclick="adicionarLinhaNaGrid()"
                    class="bg-gray-800 text-white rounded-lg px-3 py-1.5 text-sm h-fit hover:bg-gray-700">
                Adicionar à nota
            </button>
        </div>

        @error('itens') <p class="text-red-600 text-sm mb-3">{{ $message }}</p> @enderror

        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="text-left px-3 py-2">Produto</th>
                    <th class="text-left px-3 py-2">CFOP</th>
                    <th class="text-right px-3 py-2">Qtd</th>
                    <th class="text-right px-3 py-2">Unit.</th>
                    <th class="text-right px-3 py-2">Desconto</th>
                    <th class="text-right px-3 py-2">Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="linhas-grid-itens" class="divide-y divide-gray-100"></tbody>
            <tfoot class="bg-gray-50 font-medium">
                <tr>
                    <td colspan="5" class="px-3 py-2 text-right">Total da nota</td>
                    <td class="px-3 py-2 text-right" id="total-grid-itens">R$ 0,00</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        <p id="grid-vazia" class="text-center text-gray-400 py-8">Nenhum item adicionado ainda.</p>
    </div>

    <div>
        <button type="submit"
                class="bg-green-700 text-white rounded-lg px-6 py-2.5 text-sm font-medium hover:bg-green-800">
            {{ $ehEdicao ? 'Salvar Alterações' : 'Salvar Nota' }}
        </button>
    </div>
</form>

<!-- Modal de busca de produto -->
<div id="modal-busca-produto-nf" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 bg-linear-to-r from-slate-800 via-slate-900 to-slate-900">
            <h2 class="text-lg font-bold text-white">Selecionar Produto</h2>
            <button type="button" onclick="fecharModalBuscaNf()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
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

<script>
let itensNota = @json($itensIniciais);
let resultadosAtuaisNf = [];
let indiceSelecionadoNf = -1;
let timeoutBuscaNf;
let produtoSelecionadoParaEditor = null;

const inputBuscaNf = document.getElementById('input-busca-item-nf');
const inputBuscaModalNf = document.getElementById('busca-produto-modal-nf');
const linhasBuscaDivNf = document.getElementById('linhas-busca-produto-nf');
const campoCliente = document.getElementById('campo-cliente');
const campoNatureza = document.getElementById('campo-natureza');
const campoFinalidade = document.getElementById('campo-finalidade');

/**
 * Trava a área de itens até cliente + natureza + finalidade estarem preenchidos.
 * Evita o cenário de o operador montar a nota inteira e perder tudo por causa
 * de um erro de validação no cabeçalho — porque agora o cabeçalho é obrigatoriamente
 * válido ANTES de ele conseguir sequer buscar o primeiro produto.
 */
function cabecalhoValido() {
    return campoCliente.value !== '' && campoNatureza.value.trim() !== '' && campoFinalidade.value !== '';
}

function atualizarTravaCabecalho() {
    const valido = cabecalhoValido();
    inputBuscaNf.disabled = !valido;
    document.getElementById('aviso-cabecalho').classList.toggle('hidden', valido);
}

[campoCliente, campoNatureza, campoFinalidade].forEach(campo => {
    campo.addEventListener('input', atualizarTravaCabecalho);
    campo.addEventListener('change', atualizarTravaCabecalho);
});

inputBuscaNf?.addEventListener('input', () => {
    if (!document.getElementById('modal-busca-produto-nf').classList.contains('hidden')) return;
    clearTimeout(timeoutBuscaNf);
    const valor = inputBuscaNf.value.trim();
    if (/^\d+$/.test(valor)) return;
    if (valor.length < 1) return;
    timeoutBuscaNf = setTimeout(() => buscarProdutoNf(valor), 300);
});

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
        abrirEditorItem(exato);
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
    if (e.key === 'ArrowDown') { e.preventDefault(); indiceSelecionadoNf = (indiceSelecionadoNf + 1) % resultadosAtuaisNf.length; renderizarResultadosNf(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); indiceSelecionadoNf = (indiceSelecionadoNf - 1 + resultadosAtuaisNf.length) % resultadosAtuaisNf.length; renderizarResultadosNf(); }
    else if (e.key === 'Enter') { e.preventDefault(); if (indiceSelecionadoNf >= 0) selecionarResultadoNf(indiceSelecionadoNf); }
    else if (e.key === 'Escape') { fecharModalBuscaNf(); }
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
    abrirEditorItem(produto);
}

function abrirEditorItem(produto) {
    produtoSelecionadoParaEditor = produto;
    document.getElementById('editor-produto-nome').innerText = produto.nome;
    document.getElementById('editor-quantidade').value = 1;
    document.getElementById('editor-valor-unitario').value = produto.preco_venda;
    document.getElementById('editor-desconto').value = 0;
    document.getElementById('editor-cfop').value = '';
    document.getElementById('editor-item').classList.remove('hidden');
    document.getElementById('editor-cfop').focus();
}

function adicionarLinhaNaGrid() {
    const quantidade = parseFloat(document.getElementById('editor-quantidade').value) || 0;
    const valorUnitario = parseFloat(document.getElementById('editor-valor-unitario').value) || 0;
    const valorDesconto = parseFloat(document.getElementById('editor-desconto').value) || 0;
    const cfop = document.getElementById('editor-cfop').value.trim();

    if (quantidade <= 0 || valorUnitario < 0 || cfop.length !== 4) {
        alert('Preencha quantidade, valor unitário e um CFOP válido (4 dígitos).');
        return;
    }

    itensNota.push({
        produto_id: produtoSelecionadoParaEditor.id,
        nome: produtoSelecionadoParaEditor.nome,
        cfop,
        quantidade,
        valor_unitario: valorUnitario,
        valor_desconto: valorDesconto,
    });

    document.getElementById('editor-item').classList.add('hidden');
    produtoSelecionadoParaEditor = null;
    renderizarGridItens();
    inputBuscaNf.focus();
}

function removerLinhaDaGrid(index) {
    itensNota.splice(index, 1);
    renderizarGridItens();
}

function renderizarGridItens() {
    const tbody = document.getElementById('linhas-grid-itens');
    const vazia = document.getElementById('grid-vazia');

    if (itensNota.length === 0) {
        tbody.innerHTML = '';
        vazia.classList.remove('hidden');
        document.getElementById('total-grid-itens').innerText = 'R$ 0,00';
        return;
    }
    vazia.classList.add('hidden');

    let totalNota = 0;

    tbody.innerHTML = itensNota.map((item, index) => {
        const total = (item.quantidade * item.valor_unitario) - item.valor_desconto;
        totalNota += total;
        return `
            <tr>
                <td class="px-3 py-2">${item.nome}</td>
                <td class="px-3 py-2">${item.cfop}</td>
                <td class="px-3 py-2 text-right">${item.quantidade}</td>
                <td class="px-3 py-2 text-right">R$ ${item.valor_unitario.toFixed(2)}</td>
                <td class="px-3 py-2 text-right">R$ ${item.valor_desconto.toFixed(2)}</td>
                <td class="px-3 py-2 text-right font-medium">R$ ${total.toFixed(2)}</td>
                <td class="px-3 py-2 text-right">
                    <button type="button" onclick="removerLinhaDaGrid(${index})" class="text-red-600 text-xs hover:underline">remover</button>
                </td>
            </tr>
        `;
    }).join('');

    document.getElementById('total-grid-itens').innerText = 'R$ ' + totalNota.toFixed(2).replace('.', ',');
}

document.getElementById('form-nota').addEventListener('submit', function (e) {
    if (itensNota.length === 0) {
        e.preventDefault();
        alert('Adicione ao menos um item antes de salvar a nota.');
        return;
    }
    document.getElementById('itens_json').value = JSON.stringify(itensNota);
});

// Inicialização: se vier preenchido (edição) ou old() de uma tentativa anterior (criação), já libera e renderiza
atualizarTravaCabecalho();
renderizarGridItens();
</script>