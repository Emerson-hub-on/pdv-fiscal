@extends('layouts.app')

@section('titulo', 'PDV - Venda')

@section('conteudo')
<div class="pt-32 flex justify-between items-center mb-4">
<style>
    #nav-principal { display: none; }
</style>

<div class="fixed top-0 left-0 right-0 z-50
            bg-linear-to-r from-slate-800 via-slate-900 to-slate-900
            shadow-lg overflow-hidden">
    <div class="flex justify-between items-center px-6 py-4 border-b border-white/10">
        <div>
            <h1 class="text-xl font-bold text-white tracking-tight">🛒 {{ $caixa->pdv->nome }}</h1>
            <p class="text-xs text-slate-400 mt-0.5">
                Série {{ $caixa->pdv->serie_nfce }} · Próxima NFC-e nº {{ $caixa->pdv->numero_atual_nfce + 1 }}
            </p>
        </div>
        <div class="flex gap-2">
            <button id="btn-sincronizar" onclick="sincronizarAgora()"
                    class="bg-blue-700 hover:bg-blue-500 text-white text-sm font-medium px-4 py-2 transition flex items-center gap-1.5">
                🔄 Atualizar Caixa
            </button>
            <a href="{{ route('caixa.fechar-form') }}"
               class="bg-red-700 hover:bg-red-500 text-white text-sm font-medium px-4 py-2 transition flex items-center gap-1.5">
                🚪 Fechar Caixa
            </a>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 px-6 py-3 bg-black/20">
        <button onclick="abrirModalContingencias()"
                class="bg-orange-500/20 hover:bg-orange-500/30 text-orange-300 text-xs font-semibold px-3 py-1.5 transition">
            ⚠️ Contingências <span class="opacity-60">F1</span>
        </button>
        <button onclick="abrirModalInutilizacao()"
                class="bg-red-500/20 hover:bg-red-500/30 text-red-300 text-xs font-semibold px-3 py-1.5 transition">
            Inutilizar <span class="opacity-60">F2</span>
        </button>
        <button onclick="abrirModalCancelamento()"
                class="bg-red-500/20 hover:bg-red-500/30 text-red-300 text-xs font-semibold px-3 py-1.5 transition">
            Cancelar NFC-e <span class="opacity-60">F3</span>
        </button>
        <button onclick="abrirModalDescontoItem()"
                class="bg-purple-500/20 hover:bg-purple-500/30 text-purple-300 text-xs font-semibold px-3 py-1.5 transition">
            Desconto Item <span class="opacity-60">F4</span>
        </button>
        <button onclick="abrirModalDescontoGlobal()"
                class="bg-purple-500/20 hover:bg-purple-500/30 text-purple-300 text-xs font-semibold px-3 py-1.5 transition">
            Desconto Geral <span class="opacity-60">F5</span>
        </button>
        <button onclick="abrirModalCancelarItem()"
                class="bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 text-xs font-semibold px-3 py-1.5 transition">
            Cancelar Item <span class="opacity-60">F6</span>
        </button>
        <button onclick="abrirModalLimparPdv()"
                class="bg-red-600/30 hover:bg-red-600/40 text-red-200 text-xs font-bold px-3 py-1.5 transition">
            Cancelar Cupom <span class="opacity-60">F7</span>
        </button>
    </div>
</div>
</div>



<!-- Modal de busca de produto -->
<div id="modal-busca-produto" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 bg-gradient-to-r from-slate-800 via-slate-900 to-slate-900">
            <h2 class="text-lg font-bold text-white">🔍 Selecionar Produto</h2>
            <button onclick="fecharModalBusca()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>

        <div class="p-6">
            <p id="indicador-multiplicador" class="text-sm text-blue-600 font-semibold mb-2 hidden"></p>
            <input type="text" id="busca-produto-modal" placeholder="Buscar por nome, código ou código de barras..."
                   class="w-full border border-gray-200 rounded-lg px-4 py-3 text-sm mb-4 focus:ring-2 focus:ring-slate-800 outline-none transition">

            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-400 uppercase tracking-wide border-b">
                        <th class="py-2">Código</th>
                        <th class="py-2">Produto</th>
                        <th class="py-2">Preço</th>
                        <th class="py-2">Estoque</th>
                    </tr>
                </thead>
                <tbody id="linhas-busca-produto"></tbody>
            </table>

            <p class="text-xs text-gray-400 mt-3">Use ↑ ↓ para navegar e Enter para selecionar.</p>
        </div>
    </div>
</div>



<!-- Modal de contingencias -->
<div id="modal-contingencias" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded shadow-lg w-full max-w-2xl max-h-[80vh] overflow-y-auto p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Vendas em Contingência</h2>
            <button onclick="fecharModalContingencias()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

    <div class="flex justify-between items-center mb-3">
        <label class="text-sm flex items-center gap-2">
            <input type="checkbox" id="selecionar-todas" onchange="toggleTodas(this.checked)">
            Selecionar todas
        </label>
        <div class="flex items-center gap-3">
            <span id="progresso-emissao" class="text-sm text-gray-500 hidden"></span>
            <button id="btn-emitir-selecionadas" onclick="emitirSelecionadas()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm font-semibold">
                Emitir selecionadas
            </button>
        </div>
    </div>

        <div id="lista-contingencias" class="space-y-2">
            <p class="text-gray-400 text-sm">Carregando...</p>
        </div>
    </div>
</div>



<!-- Modal de autorização do supervisor (compartilhado entre F4 e F5) -->
<div id="modal-autorizacao" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded shadow-lg w-full max-w-sm p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Autorização do Supervisor</h2>
            <button onclick="fecharModalAutorizacao()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <p class="text-sm text-gray-500 mb-4" id="autorizacao-descricao"></p>

        <label class="block text-sm font-medium mb-1">Usuário</label>
        <input type="text" id="autorizacao-usuario" class="w-full border rounded px-3 py-2 mb-3">

        <label class="block text-sm font-medium mb-1">Senha</label>
        <input type="password" id="autorizacao-senha" class="w-full border rounded px-3 py-2 mb-4">

        <p id="autorizacao-erro" class="text-red-600 text-sm mb-3 hidden"></p>

        <button onclick="confirmarAutorizacao()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded font-semibold">
            Autorizar
        </button>
    </div>
</div>

<!-- Modal desconto em item (F4) - so aparece depois de autorizado -->
<div id="modal-desconto-item" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded shadow-lg w-full max-w-sm p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Desconto em Item</h2>
            <button onclick="fecharModalDescontoItem()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <label class="block text-sm font-medium mb-1">Número do item</label>
        <input type="number" min="1" id="desconto-item-numero" class="w-full border rounded px-3 py-2 mb-1">
        <p id="desconto-item-preview" class="text-xs text-gray-500 mb-3"></p>

        <label class="block text-sm font-medium mb-1">Valor do desconto (R$)</label>
        <input type="number" step="0.01" min="0" id="desconto-item-valor" class="w-full border rounded px-3 py-2 mb-4">

        <p id="desconto-item-erro" class="text-red-600 text-sm mb-3 hidden"></p>

        <button onclick="confirmarDescontoItem()" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 rounded font-semibold">
            Aplicar desconto
        </button>
    </div>
</div>

<!-- Modal desconto global (F5) - so aparece depois de autorizado -->
<div id="modal-desconto-global" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded shadow-lg w-full max-w-sm p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Desconto Geral</h2>
            <button onclick="fecharModalDescontoGlobal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <label class="block text-sm font-medium mb-1">Valor do desconto (R$)</label>
        <input type="number" step="0.01" min="0" id="desconto-global-valor" class="w-full border rounded px-3 py-2 mb-4">

        <p id="desconto-global-erro" class="text-red-600 text-sm mb-3 hidden"></p>

        <button onclick="confirmarDescontoGlobal()" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 rounded font-semibold">
            Aplicar desconto
        </button>
    </div>
</div>


<!-- Modal cancelar item (F6) - so aparece depois de autorizado -->
<div id="modal-cancelar-item" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded shadow-lg w-full max-w-sm p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Cancelar Item</h2>
            <button onclick="fecharModalCancelarItem()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <label class="block text-sm font-medium mb-1">Número do item</label>
        <input type="number" min="1" id="cancelar-item-numero" class="w-full border rounded px-3 py-2 mb-1">
        <p id="cancelar-item-preview" class="text-xs text-gray-500 mb-4"></p>

        <p id="cancelar-item-erro" class="text-red-600 text-sm mb-3 hidden"></p>

        <button onclick="confirmarCancelarItem()" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 rounded font-semibold">
            Cancelar item
        </button>
    </div>
</div>




<!-- Modal de inutilização -->

<div id="modal-inutilizacao" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded shadow-lg w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Inutilizar Numeração NFC-e</h2>
            <button onclick="fecharModalInutilizacao()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div class="bg-yellow-100 text-yellow-800 border border-yellow-300 rounded px-3 py-2 mb-4 text-xs">
            Atenção: esta ação é irreversível perante a SEFAZ. Use apenas para números pulados ou com falha técnica que nunca chegaram a ser autorizados.
        </div>

        <label class="block text-sm font-medium mb-1">Número inicial</label>
        <input type="number" id="inut-numero-inicial" class="w-full border rounded px-3 py-2 mb-3">

        <label class="block text-sm font-medium mb-1">Número final</label>
        <input type="number" id="inut-numero-final" class="w-full border rounded px-3 py-2 mb-3">

        <label class="block text-sm font-medium mb-1">Justificativa (mín. 15 caracteres)</label>
        <textarea id="inut-justificativa" rows="3" class="w-full border rounded px-3 py-2 mb-4"></textarea>

        <p id="inut-erro" class="text-red-600 text-sm mb-3 hidden"></p>

        <button onclick="confirmarInutilizacao()" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 rounded font-semibold">
            Inutilizar
        </button>
    </div>
</div>

<!-- Modal de cancelamento -->

<div id="modal-cancelamento" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded shadow-lg w-full max-w-2xl max-h-[80vh] overflow-y-auto p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Cancelar NFC-e</h2>
            <button onclick="fecharModalCancelamento()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <p class="text-xs text-gray-400 mb-3">Últimas 20 vendas emitidas. Clique em uma para cancelar.</p>
        <div id="lista-cancelamento" class="space-y-2">
            <p class="text-gray-400 text-sm">Carregando...</p>
        </div>
    </div>
</div>
    

    <div class="grid grid-cols-3 gap-6 shadow-md">
        <div class="col-span-2 bg-white rounded-xl shadow-lg overflow-hidden">
<div class="p-4">
    <div class="relative mb-4">
        <input type="text" id="busca-produto" placeholder="🔍 Buscar por nome, código ou código de barras..."
            class="w-full border border-gray-200 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition" autofocus>
    </div>
</div>

<table class="w-full">
    <thead class="bg-gradient-to-r from-slate-800 via-slate-900 to-slate-900">
        <tr class="text-left text-xs text-slate-300 uppercase tracking-wide">
            <th class="py-3 pl-4">Item</th>
            <th class="py-3">Produto</th>
            <th class="py-3">Qtd</th>
            <th class="py-3">Preço</th>
            <th class="py-3">Desconto</th>
            <th class="py-3 pr-4">Subtotal</th>
        </tr>
    </thead>
    <tbody id="linhas-carrinho"></tbody>
</table>
<p id="carrinho-vazio" class="text-center text-gray-400 py-16">🛒 Nenhum item adicionado.</p></table>
            <p id="carrinho-vazio" class="text-center text-gray-300 py-10">Nenhum item adicionado.</p>
        </div>

<div class="bg-white rounded-xl shadow-lg p-5 h-fit">
    <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Total</p>
    <p id="total-venda" class="text-4xl font-bold text-slate-900 mb-5">R$ 0,00</p>

    <div class="bg-gray-50 rounded-lg p-3 text-sm mb-5 space-y-1.5">
        <p class="flex justify-between">Desconto por item <strong id="desconto-item-exibido" class="text-green-600">R$ 0,00</strong></p>
        <p class="flex justify-between">Desconto global <strong id="desconto-global-exibido" class="text-green-600">R$ 0,00</strong></p>
        <p class="flex justify-between border-t border-gray-200 pt-1.5 mt-1.5">Total de desconto <strong id="desconto-total-exibido" class="text-green-700">R$ 0,00</strong></p>
    </div>

    <button id="btn-prosseguir" onclick="irParaPagamento()"
            class="w-full bg-emerald-600 hover:bg-emerald-500 text-white py-3.5 rounded-lg font-bold text-lg transition shadow-md disabled:opacity-50">
        Prosseguir para pagamento →
    </button>

    <p id="erro-itens" class="text-red-600 text-sm mt-2 hidden"></p>
</div>
    </div>
@endsection

@section('scripts')


@php
    $itensParaCarrinho = collect($itensIniciais)->map(fn($i) => [
        'chave' => $i['produto_id'] . '-' . ($i['produto_variante_id'] ?? '0'),
        'produto_id' => $i['produto_id'],
        'produto_variante_id' => $i['produto_variante_id'],
        'nome' => $i['nome'],
        'preco' => $i['preco'],
        'quantidade' => $i['quantidade'],
        'desconto' => $i['desconto_bruto'] ?? 0,
    ])->values();
@endphp

<script>


let carrinho = @json($itensParaCarrinho);
let quantidadeMultiplicador = 1;
let resultadosAtuais = [];
let indiceSelecionado = -1;
let descontoGlobal = {{ $descontoGlobalInicial }};
let tipoDescontoPendente = null;
let timeoutBusca;

const inputBusca = document.getElementById('busca-produto');
const inputBuscaModal = document.getElementById('busca-produto-modal');
const linhasBuscaDiv = document.getElementById('linhas-busca-produto');


document.addEventListener('keydown', (e) => {
    if (e.key === 'F1') { e.preventDefault(); abrirModalContingencias(); }
    if (e.key === 'F2') { e.preventDefault(); abrirModalInutilizacao(); }
    if (e.key === 'F3') { e.preventDefault(); abrirModalCancelamento(); }
    if (e.key === 'F4') { e.preventDefault(); abrirModalDescontoItem(); }
    if (e.key === 'F5') { e.preventDefault(); abrirModalDescontoGlobal(); }
    if (e.key === 'F6') { e.preventDefault(); abrirModalCancelarItem(); }
    if (e.key === 'F7') { e.preventDefault(); abrirModalLimparPdv(); }
    if (e.key === 'Escape') { fecharTodosModais(); }
});


inputBuscaModal.addEventListener('keydown', (e) => {
    // Enter forca a busca imediata, mesmo se o texto for so numeros
    // (util pra codigo de produto puramente numerico, sem multiplicador)
    if (e.key === 'Enter' && resultadosAtuais.length === 0 && inputBuscaModal.value.trim().length > 0) {
        e.preventDefault();
        const { multiplicador, termo } = interpretarBusca(inputBuscaModal.value);
        quantidadeMultiplicador = multiplicador;
        if (termo.length > 0) {
            buscarProduto(termo);
        }
        return;
    }

    if (resultadosAtuais.length === 0) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        indiceSelecionado = (indiceSelecionado + 1) % resultadosAtuais.length;
        renderizarResultados();
        scrollParaSelecionado();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        indiceSelecionado = (indiceSelecionado - 1 + resultadosAtuais.length) % resultadosAtuais.length;
        renderizarResultados();
        scrollParaSelecionado();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (indiceSelecionado >= 0) {
            selecionarResultado(indiceSelecionado);
        }
    }
});


inputBusca.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && inputBusca.value.trim().length > 0) {
        e.preventDefault();
        const { multiplicador, termo } = interpretarBusca(inputBusca.value);
        quantidadeMultiplicador = multiplicador;
        if (termo.length > 0) {
            buscarProduto(termo);
        }
    }
});

function interpretarBusca(valor) {
    const match = valor.match(/^(\d+)\*(.*)$/);

    if (match) {
        return {
            multiplicador: parseInt(match[1]),
            termo: match[2].trim(),
        };
    }

    return { multiplicador: 1, termo: valor.trim() };
}



function atualizarIndicadorMultiplicador() {
    const indicador = document.getElementById('indicador-multiplicador');

    if (quantidadeMultiplicador > 1) {
        indicador.innerText = `Quantidade: ${quantidadeMultiplicador}x`;
        indicador.classList.remove('hidden');
    } else {
        indicador.classList.add('hidden');
    }
}


function scrollParaSelecionado() {
    const elemento = linhasBuscaDiv.querySelector(`[data-index="${indiceSelecionado}"]`);
    elemento?.scrollIntoView({ block: 'nearest' });
}



function fecharTodosModais() {
    const idsModais = [
        'modal-contingencias',
        'modal-inutilizacao',
        'modal-cancelamento',
        'modal-desconto-item',
        'modal-desconto-global',
        'modal-cancelar-item',
        'modal-autorizacao',
        'modal-busca-produto',
    ];

    idsModais.forEach(id => {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    });

    tipoDescontoPendente = null;
}

inputBusca.addEventListener('input', () => {
    clearTimeout(timeoutBusca);
    const valor = inputBusca.value;

    // Enquanto for só numeros (sem * ainda), aguarda - pode ser o começo de "3*produto"
    if (/^\d+$/.test(valor)) {
        return;
    }

    const { multiplicador, termo } = interpretarBusca(valor);
    quantidadeMultiplicador = multiplicador;

    if (termo.length < 1) {
        return;
    }

    timeoutBusca = setTimeout(() => buscarProduto(termo), 300);
});



async function buscarProduto(termo) {
    const resp = await fetch(`{{ route('vendas.buscar-produto') }}?termo=${encodeURIComponent(termo)}`);
    const produtos = await resp.json();

    resultadosAtuais = [];
    produtos.forEach(p => {
        if (p.tem_variacao && p.variantes.length > 0) {
            p.variantes.forEach(v => resultadosAtuais.push({ produto: p, variante: v }));
        } else {
            resultadosAtuais.push({ produto: p, variante: null });
        }
    });

    indiceSelecionado = resultadosAtuais.length > 0 ? 0 : -1;

    abrirModalBusca();
    renderizarResultados();
}


function abrirModalBusca() {
    document.getElementById('modal-busca-produto').classList.remove('hidden');
    document.getElementById('modal-busca-produto').classList.add('flex');
    inputBuscaModal.value = inputBusca.value;
    inputBuscaModal.focus();
}

function fecharModalBusca() {
    document.getElementById('modal-busca-produto').classList.add('hidden');
    document.getElementById('modal-busca-produto').classList.remove('flex');
    inputBusca.value = '';
    inputBuscaModal.value = '';
    quantidadeMultiplicador = 1;
    document.getElementById('indicador-multiplicador').classList.add('hidden');
    inputBusca.focus();
}


function renderizarResultados() {
    if (resultadosAtuais.length === 0) {
        linhasBuscaDiv.innerHTML = '<tr><td colspan="4" class="p-3 text-sm text-gray-400 text-center">Nenhum produto encontrado.</td></tr>';
        return;
    }

linhasBuscaDiv.innerHTML = resultadosAtuais.map((op, index) => {
    const destacado = index === indiceSelecionado;
    const codigo = op.produto.codigo_barras || op.produto.codigo_interno;
    const nome = op.variante
        ? `${op.produto.nome} — ${op.variante.cor ?? ''} ${op.variante.tamanho ?? ''}`
        : op.produto.nome;
    const preco = Number(op.produto.preco_venda).toFixed(2);
    const estoque = op.variante ? op.variante.estoque : op.produto.estoque;

    return `
        <tr class="cursor-pointer border-b border-gray-100 transition ${destacado ? 'bg-slate-800 text-white' : 'hover:bg-gray-50'}"
            data-index="${index}"
            onclick="selecionarResultado(${index})">
            <td class="py-3 font-mono text-sm ${destacado ? 'text-slate-300' : 'text-gray-500'}">${codigo}</td>
            <td class="py-3 font-medium">${nome}</td>
            <td class="py-3 ${destacado ? 'text-emerald-300' : 'text-emerald-600'} font-semibold">R$ ${preco}</td>
            <td class="py-3 ${destacado ? 'text-slate-300' : 'text-gray-500'}">${estoque}</td>
        </tr>
    `;
}).join('');
}


function selecionarResultado(index) {
    const op = resultadosAtuais[index];
    adicionarAoCarrinho(op.produto, op.variante);
    resultadosAtuais = [];
    indiceSelecionado = -1;
    fecharModalBusca();
}


function adicionarAoCarrinho(produto, variante) {
    const chaveBase = produto.id + '-' + (variante ? variante.id : '0');
    const existente = carrinho.find(i => i.chave === chaveBase && !i.cancelado);

    if (existente) {
        existente.quantidade += quantidadeMultiplicador;
    } else {
        const chave = carrinho.some(i => i.chave === chaveBase)
            ? chaveBase + '-' + Date.now()
            : chaveBase;
        carrinho.push({
            chave,
            produto_id: produto.id,
            produto_variante_id: variante ? variante.id : null,
            nome: produto.nome + (variante ? ` — ${variante.cor ?? ''} ${variante.tamanho ?? ''}` : ''),
            preco: parseFloat(produto.preco_venda),
            quantidade: quantidadeMultiplicador,
        });
    }

    quantidadeMultiplicador = 1; // reseta pra proxima busca
    renderizarCarrinho();
}


function renderizarCarrinho() {
    const tbody = document.getElementById('linhas-carrinho');
    const vazio = document.getElementById('carrinho-vazio');

    if (carrinho.length === 0) {
        tbody.innerHTML = '';
        vazio.classList.remove('hidden');
        atualizarTotais();
        return;
    }

    vazio.classList.add('hidden');

    const itensCalculados = calcularItensComDescontoRateado();

tbody.innerHTML = itensCalculados.map((item, index) => `
    <tr class="border-b border-gray-100 hover:bg-slate-50 transition ${item.cancelado ? 'bg-red-50' : ''}">
        <td class="py-3 pl-4 text-gray-400 font-mono text-sm">${index + 1}</td>
        <td class="py-3 font-medium ${item.cancelado ? 'text-red-500 line-through' : 'text-gray-800'}">${item.nome}</td>
        <td class="py-3 ${item.cancelado ? 'text-red-500 line-through' : 'text-gray-600'}">${item.quantidade}</td>
        <td class="py-3 ${item.cancelado ? 'text-red-500 line-through' : 'text-gray-600'}">R$ ${item.preco.toFixed(2)}</td>
        <td class="py-3 ${item.cancelado ? 'text-red-500 line-through' : (item.descontoEfetivo > 0 ? 'text-green-600 font-medium' : 'text-gray-400')}">R$ ${item.descontoEfetivo.toFixed(2)}</td>
        <td class="py-3 pr-4 font-semibold ${item.cancelado ? 'text-red-500 line-through' : 'text-gray-900'}">R$ ${item.subtotalLiquido.toFixed(2)}</td>
        ${item.cancelado ? `<td class="py-3 pr-4 text-red-600 text-xs font-bold text-right">✕ Cancelado</td>` : ''}
    </tr>
`).join('');

    atualizarTotais();
}



function abrirModalContingencias() {
    document.getElementById('modal-contingencias').classList.remove('hidden');
    document.getElementById('modal-contingencias').classList.add('flex');
    carregarContingencias();
}

function fecharModalContingencias() {
    document.getElementById('modal-contingencias').classList.add('hidden');
    document.getElementById('modal-contingencias').classList.remove('flex');
}

async function carregarContingencias() {
    const resp = await fetch('{{ route("contingencias.listar") }}');
    const vendas = await resp.json();

    const container = document.getElementById('lista-contingencias');

    if (vendas.length === 0) {
        container.innerHTML = '<p class="text-gray-400 text-sm">Nenhuma venda em contingência.</p>';
        return;
    }

    container.innerHTML = vendas.map(v => `
        <div class="border rounded">
            <div class="flex items-center justify-between p-3 cursor-pointer hover:bg-gray-50" onclick="toggleExpandir(${v.id})">
                <div class="flex items-center gap-3">
                    <input type="checkbox" class="check-contingencia" value="${v.id}" onclick="event.stopPropagation()">
                    <div>
                        <p class="text-sm font-medium">
                            NFC-e nº ${v.numero_nfce ?? '-'} (série ${v.serie_nfce ?? '-'}) — R$ ${Number(v.total).toFixed(2)}
                        </p>
                        <p class="text-xs text-gray-400">${v.criada_em}</p>
                        ${v.chave_nfe ? `<p class="text-xs text-gray-400 break-all">Chave: ${v.chave_nfe}</p>` : ''}
                    </div>
                </div>
                <span class="text-gray-400 text-xs">▼</span>
            </div>
            <div id="detalhe-${v.id}" class="hidden border-t bg-gray-50 p-3 text-sm">
                <p class="text-red-600 mb-2"><strong>Motivo:</strong> ${v.motivo ?? 'Não informado'}</p>
                <p class="text-gray-600"><strong>Itens:</strong> ${v.itens.join(', ')}</p>
            </div>
        </div>
    `).join('');
}

function toggleExpandir(id) {
    document.getElementById(`detalhe-${id}`).classList.toggle('hidden');
}

function toggleTodas(marcado) {
    document.querySelectorAll('.check-contingencia').forEach(cb => cb.checked = marcado);
}

async function emitirSelecionadas() {
    const ids = Array.from(document.querySelectorAll('.check-contingencia:checked')).map(cb => cb.value);

    if (ids.length === 0) {
        alert('Selecione ao menos uma venda.');
        return;
    }

    const btn = document.getElementById('btn-emitir-selecionadas');
    const progresso = document.getElementById('progresso-emissao');

    btn.disabled = true;
    progresso.classList.remove('hidden');

    let sucesso = 0;
    let falha = 0;

    for (let i = 0; i < ids.length; i++) {
        const id = ids[i];
        progresso.innerText = `${i + 1} / ${ids.length} processando...`;

        try {
            const resp = await fetch(`/contingencias/${id}/reenviar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
            });

            const resultado = await resp.json();

            if (resultado.sucesso) {
                sucesso++;
                marcarLinhaEmitida(id);
            } else {
                falha++;
                atualizarMotivoLinha(id, resultado.erro);
            }
        } catch (e) {
            falha++;
            atualizarMotivoLinha(id, 'Erro de conexão ao tentar emitir.');
        }

        progresso.innerText = `${i + 1} / ${ids.length} — ${sucesso} emitida(s), ${falha} pendente(s)`;
    }

    btn.disabled = false;

    setTimeout(() => {
        progresso.classList.add('hidden');
        carregarContingencias(); // atualiza a lista, removendo as que foram emitidas
    }, 1500);
}

function marcarLinhaEmitida(id) {
    const linha = document.querySelector(`.check-contingencia[value="${id}"]`)?.closest('.border');
    if (linha) {
        linha.classList.add('opacity-40');
        linha.querySelector('.check-contingencia').disabled = true;
        const status = document.createElement('span');
        status.className = 'text-green-600 text-xs ml-2';
        status.innerText = '✓ Emitida';
        linha.querySelector('.flex.items-center.gap-3')?.appendChild(status);
    }
}

function atualizarMotivoLinha(id, motivo) {
    const detalhe = document.getElementById(`detalhe-${id}`);
    if (detalhe) {
        detalhe.querySelector('p').innerHTML = `<strong>Motivo:</strong> ${motivo}`;
        detalhe.classList.remove('hidden'); // expande automaticamente pra mostrar o novo motivo
    }
}


function abrirModalInutilizacao() {
    document.getElementById('modal-inutilizacao').classList.remove('hidden');
    document.getElementById('modal-inutilizacao').classList.add('flex');
}

function fecharModalInutilizacao() {
    document.getElementById('modal-inutilizacao').classList.add('hidden');
    document.getElementById('modal-inutilizacao').classList.remove('flex');
}

async function confirmarInutilizacao() {
    const numeroInicial = document.getElementById('inut-numero-inicial').value;
    const numeroFinal = document.getElementById('inut-numero-final').value;
    const justificativa = document.getElementById('inut-justificativa').value;
    const erroP = document.getElementById('inut-erro');

    erroP.classList.add('hidden');

    if (!numeroInicial || !numeroFinal || justificativa.length < 15) {
        erroP.innerText = 'Preencha os números e uma justificativa com pelo menos 15 caracteres.';
        erroP.classList.remove('hidden');
        return;
    }

    if (!confirm(`Confirma a inutilização da numeração ${numeroInicial} a ${numeroFinal}? Esta ação não pode ser desfeita.`)) {
        return;
    }

    const resp = await fetch('{{ route("inutilizacao.executar") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({ numero_inicial: numeroInicial, numero_final: numeroFinal, justificativa }),
    });

    const resultado = await resp.json();

    if (resultado.sucesso) {
        alert('Numeração inutilizada com sucesso. Protocolo: ' + resultado.protocolo);
        fecharModalInutilizacao();
    } else {
        erroP.innerText = resultado.erro;
        erroP.classList.remove('hidden');
    }
}


function abrirModalCancelamento() {
    document.getElementById('modal-cancelamento').classList.remove('hidden');
    document.getElementById('modal-cancelamento').classList.add('flex');
    carregarVendasCancelamento();
}

function fecharModalCancelamento() {
    document.getElementById('modal-cancelamento').classList.add('hidden');
    document.getElementById('modal-cancelamento').classList.remove('flex');
}

async function carregarVendasCancelamento() {
    const resp = await fetch('{{ route("cancelamento.listar") }}');
    const vendas = await resp.json();

    const container = document.getElementById('lista-cancelamento');

    if (vendas.length === 0) {
        container.innerHTML = '<p class="text-gray-400 text-sm">Nenhuma venda emitida encontrada.</p>';
        return;
    }

    container.innerHTML = vendas.map(v => `
        <div class="border rounded">
            <div class="flex items-center justify-between p-3 cursor-pointer hover:bg-gray-50" onclick="toggleExpandirCancelamento(${v.id})">
                <div>
                    <p class="text-sm font-medium">NFC-e nº ${v.numero_nfce} — R$ ${Number(v.total).toFixed(2)}</p>
                    <p class="text-xs text-gray-400">${v.criada_em}</p>
                    <p class="text-xs text-gray-400 break-all">Chave: ${v.chave_nfe}</p>
                </div>
                <span class="text-gray-400 text-xs">▼</span>
            </div>
            <div id="cancel-detalhe-${v.id}" class="hidden border-t bg-gray-50 p-3 text-sm">
                <p class="text-gray-600 mb-2"><strong>Itens:</strong> ${v.itens.join(', ')}</p>
                <label class="block text-xs font-medium mb-1">Justificativa (mín. 15 caracteres)</label>
                <textarea id="just-${v.id}" rows="2" class="w-full border rounded px-2 py-1 mb-2 text-sm"></textarea>
                <p id="cancel-erro-${v.id}" class="text-red-600 text-xs mb-2 hidden"></p>
                <button onclick="confirmarCancelamento(${v.id})" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded text-xs font-semibold">
                    Confirmar cancelamento
                </button>
            </div>
        </div>
    `).join('');
}

function toggleExpandirCancelamento(id) {
    document.getElementById(`cancel-detalhe-${id}`).classList.toggle('hidden');
}

async function confirmarCancelamento(id) {
    const justificativa = document.getElementById(`just-${id}`).value;
    const erroP = document.getElementById(`cancel-erro-${id}`);

    if (justificativa.length < 15) {
        erroP.innerText = 'A justificativa precisa ter no mínimo 15 caracteres.';
        erroP.classList.remove('hidden');
        return;
    }

    if (!confirm('Confirma o cancelamento desta NFC-e? Esta ação é irreversível.')) return;

    const resp = await fetch(`/cancelamento/${id}/cancelar`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({ justificativa }),
    });

    const resultado = await resp.json();

    if (resultado.sucesso) {
        alert('NFC-e cancelada com sucesso. Protocolo: ' + resultado.protocolo);
        carregarVendasCancelamento();
    } else {
        erroP.innerText = resultado.erro;
        erroP.classList.remove('hidden');
    }
}



function calcularItensComDescontoRateado() {
    const itensAtivos = carrinho.filter(i => !i.cancelado);
    const subtotalBrutoGeral = itensAtivos.reduce((soma, i) => soma + (i.preco * i.quantidade), 0);

    const rateios = {};
    let somaRateios = 0;

    itensAtivos.forEach((item, index) => {
        const subtotalBruto = item.preco * item.quantidade;
        let rateio;

        if (index === itensAtivos.length - 1) {
            rateio = descontoGlobal - somaRateios;
        } else {
            rateio = subtotalBrutoGeral > 0
                ? Math.round((descontoGlobal * (subtotalBruto / subtotalBrutoGeral)) * 100) / 100
                : 0;
            somaRateios += rateio;
        }

        rateios[item.chave] = rateio;
    });

    return carrinho.map(item => {
        const subtotalBruto = item.preco * item.quantidade;

        if (item.cancelado) {
            return { ...item, descontoEfetivo: 0, subtotalBruto, subtotalLiquido: 0 };
        }

        const descontoItem = item.desconto ?? 0;
        const rateio = rateios[item.chave] ?? 0;
        const descontoEfetivo = Math.min(descontoItem + rateio, subtotalBruto);

        return { ...item, descontoEfetivo, subtotalBruto, subtotalLiquido: subtotalBruto - descontoEfetivo };
    });
}



function atualizarTotais() {
    const itensCalculados = calcularItensComDescontoRateado();
    const totalLiquido = itensCalculados.reduce((soma, i) => soma + i.subtotalLiquido, 0);

    // Desconto lançado item a item (F4), sem contar a fatia do rateio do desconto global
    const descontoPorItem = carrinho.reduce((soma, i) => soma + (i.desconto ?? 0), 0);

    // Desconto geral lançado via F5
    const descontoGlobalAplicado = descontoGlobal;

    document.getElementById('total-venda').innerText = 'R$ ' + totalLiquido.toFixed(2).replace('.', ',');
    document.getElementById('desconto-item-exibido').innerText = 'R$ ' + descontoPorItem.toFixed(2).replace('.', ',');
    document.getElementById('desconto-global-exibido').innerText = 'R$ ' + descontoGlobalAplicado.toFixed(2).replace('.', ',');
    document.getElementById('desconto-total-exibido').innerText = 'R$ ' + (descontoPorItem + descontoGlobalAplicado).toFixed(2).replace('.', ',');

}


function abrirModalDescontoItem() {
    if (carrinho.length === 0) {
        alert('Adicione um item ao carrinho primeiro.');
        return;
    }
    tipoDescontoPendente = 'item';
    abrirModalAutorizacao('Autorização necessária para aplicar desconto em item.');
}


function abrirModalCancelarItem() {
    if (carrinho.length === 0) {
        alert('Não há itens no carrinho.');
        return;
    }
    tipoDescontoPendente = 'cancelar_item';
    abrirModalAutorizacao('Autorização necessária para cancelar um item.');
}

function abrirModalLimparPdv() {
    if (carrinho.length === 0) {
        alert('O carrinho já está vazio.');
        return;
    }
    tipoDescontoPendente = 'limpar_pdv';
    abrirModalAutorizacao('Autorização necessária para cancelar o cupom (limpar todos os itens).');
}


document.getElementById('desconto-item-numero')?.addEventListener('input', function () {
    const indice = parseInt(this.value) - 1;
    const preview = document.getElementById('desconto-item-preview');
    const item = carrinho[indice];

    preview.innerText = item ? `→ ${item.nome}` : 'Número inválido.';
    preview.className = item ? 'text-xs text-green-600 mb-3' : 'text-xs text-red-500 mb-3';
});



function fecharModalDescontoItem() {
    document.getElementById('modal-desconto-item').classList.add('hidden');
    document.getElementById('modal-desconto-item').classList.remove('flex');
}

function confirmarDescontoItem() {
    const numero = parseInt(document.getElementById('desconto-item-numero').value);
    const indice = numero - 1;
    const valor = parseFloat(document.getElementById('desconto-item-valor').value) || 0;
    const erroP = document.getElementById('desconto-item-erro');

    const item = carrinho[indice];

    if (!item) {
        erroP.innerText = 'Número de item inválido.';
        erroP.classList.remove('hidden');
        return;
    }

    const subtotalBruto = item.preco * item.quantidade;
    item.desconto = Math.min(valor, subtotalBruto);

    renderizarCarrinho();
    fecharModalDescontoItem();
}

function abrirModalDescontoGlobal() {
    if (carrinho.length === 0) {
        alert('Adicione um item ao carrinho primeiro.');
        return;
    }
    tipoDescontoPendente = 'global';
    abrirModalAutorizacao('Autorização necessária para aplicar desconto geral.');
}

function fecharModalDescontoGlobal() {
    document.getElementById('modal-desconto-global').classList.add('hidden');
    document.getElementById('modal-desconto-global').classList.remove('flex');
}

function abrirModalAutorizacao(descricao) {
    document.getElementById('autorizacao-descricao').innerText = descricao;
    document.getElementById('autorizacao-usuario').value = '';
    document.getElementById('autorizacao-senha').value = '';
    document.getElementById('autorizacao-erro').classList.add('hidden');

    document.getElementById('modal-autorizacao').classList.remove('hidden');
    document.getElementById('modal-autorizacao').classList.add('flex');
}


async function confirmarAutorizacao() {
    const usuario = document.getElementById('autorizacao-usuario').value;
    const senha = document.getElementById('autorizacao-senha').value;
    const erroP = document.getElementById('autorizacao-erro');

    if (!usuario || !senha) {
        erroP.innerText = 'Informe usuário e senha do supervisor.';
        erroP.classList.remove('hidden');
        return;
    }

    try {
        const resp = await fetch('{{ route("supervisor.autorizar") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({ username: usuario, password: senha }),
        });

        const resultado = await resp.json();

        if (!resultado.autorizado) {
            erroP.innerText = 'Usuário ou senha do supervisor inválidos.';
            erroP.classList.remove('hidden');
            return;
        }
    } catch (e) {
        erroP.innerText = 'Erro de conexão ao validar supervisor.';
        erroP.classList.remove('hidden');
        return;
    }

    // Autorizado - fecha o modal de senha e abre o modal de lançamento correspondente
    document.getElementById('modal-autorizacao').classList.add('hidden');
    document.getElementById('modal-autorizacao').classList.remove('flex');

    if (tipoDescontoPendente === 'item') {
        abrirLancamentoDescontoItem();
    } else if (tipoDescontoPendente === 'global') {
        abrirLancamentoDescontoGlobal();
    } else if (tipoDescontoPendente === 'cancelar_item') {
        abrirLancamentoCancelarItem();
    } else if (tipoDescontoPendente === 'limpar_pdv') {
        executarLimparPdv();
    }
}



function abrirLancamentoDescontoItem() {
    document.getElementById('desconto-item-numero').value = '';
    document.getElementById('desconto-item-preview').innerText = '';
    document.getElementById('desconto-item-valor').value = '';
    document.getElementById('desconto-item-erro').classList.add('hidden');

    document.getElementById('modal-desconto-item').classList.remove('hidden');
    document.getElementById('modal-desconto-item').classList.add('flex');
}


function abrirLancamentoDescontoGlobal() {
    document.getElementById('desconto-global-valor').value = descontoGlobal || '';
    document.getElementById('desconto-global-erro').classList.add('hidden');

    document.getElementById('modal-desconto-global').classList.remove('hidden');
    document.getElementById('modal-desconto-global').classList.add('flex');
}


document.getElementById('desconto-item-numero')?.addEventListener('input', function () {
    const indice = parseInt(this.value) - 1;
    const preview = document.getElementById('desconto-item-preview');
    const item = carrinho[indice];

    preview.innerText = item ? `→ ${item.nome}` : 'Número inválido.';
    preview.className = item ? 'text-xs text-green-600 mb-3' : 'text-xs text-red-500 mb-3';
});



function fecharModalAutorizacao() {
    document.getElementById('modal-autorizacao').classList.add('hidden');
    document.getElementById('modal-autorizacao').classList.remove('flex');
    tipoDescontoPendente = null;
}

function confirmarDescontoGlobal() {
    const valor = parseFloat(document.getElementById('desconto-global-valor').value) || 0;

    descontoGlobal = valor;
    atualizarTotais();
    fecharModalDescontoGlobal();
}


function abrirLancamentoCancelarItem() {
    document.getElementById('cancelar-item-numero').value = '';
    document.getElementById('cancelar-item-preview').innerText = '';
    document.getElementById('cancelar-item-erro').classList.add('hidden');

    document.getElementById('modal-cancelar-item').classList.remove('hidden');
    document.getElementById('modal-cancelar-item').classList.add('flex');
}

function fecharModalCancelarItem() {
    document.getElementById('modal-cancelar-item').classList.add('hidden');
    document.getElementById('modal-cancelar-item').classList.remove('flex');
}

document.getElementById('cancelar-item-numero')?.addEventListener('input', function () {
    const indice = parseInt(this.value) - 1;
    const preview = document.getElementById('cancelar-item-preview');
    const item = carrinho[indice];

    preview.innerText = item ? `→ ${item.nome} (Qtd: ${item.quantidade})` : 'Número inválido.';
    preview.className = item ? 'text-xs text-green-600 mb-4' : 'text-xs text-red-500 mb-4';
});

function confirmarCancelarItem() {
    const numero = parseInt(document.getElementById('cancelar-item-numero').value);
    const indice = numero - 1;
    const erroP = document.getElementById('cancelar-item-erro');

    const item = carrinho[indice];

    if (!item) {
        erroP.innerText = 'Número de item inválido.';
        erroP.classList.remove('hidden');
        return;
    }

    if (item.cancelado) {
        erroP.innerText = 'Este item já está cancelado.';
        erroP.classList.remove('hidden');
        return;
    }

    item.cancelado = true;

    renderizarCarrinho();
    fecharModalCancelarItem();
}


async function executarLimparPdv() {
    if (!confirm('Confirma o cancelamento do cupom? Todos os itens do carrinho serão removidos.')) {
        return;
    }

    carrinho = [];
    descontoGlobal = 0;

    await fetch('{{ route("vendas.limpar-sessao") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
    });

    renderizarCarrinho();
}



async function irParaPagamento() {
    const itensAtivos = calcularItensComDescontoRateado().filter(i => !i.cancelado);

    if (itensAtivos.length === 0) {
        document.getElementById('erro-itens').innerText = 'Adicione ao menos um item antes de prosseguir.';
        document.getElementById('erro-itens').classList.remove('hidden');
        return;
    }

    const descontoPorItem = carrinho.reduce((soma, i) => soma + (i.desconto ?? 0), 0);
    const total = itensAtivos.reduce((soma, i) => soma + i.subtotalLiquido, 0);

    const payload = {
        itens: itensAtivos.map(i => ({
            produto_id: i.produto_id,
            produto_variante_id: i.produto_variante_id,
            nome: i.nome,
            quantidade: i.quantidade,
            preco: i.preco,
            desconto: Math.round(i.descontoEfetivo * 100) / 100, // usado na finalizacao da venda
            desconto_bruto: i.desconto ?? 0, // usado so pra recarregar o carrinho depois
            subtotal: Math.round(i.subtotalLiquido * 100) / 100,
        })),
        desconto_item: descontoPorItem,
        desconto_global: descontoGlobal,
        total: Math.round(total * 100) / 100,
    };

    const resp = await fetch('{{ route("vendas.preparar-pagamento") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify(payload),
    });

    if (resp.ok) {
        window.location.href = '{{ route("vendas.pagamento") }}';
    } else {
        document.getElementById('erro-itens').innerText = 'Erro ao prosseguir. Tente novamente.';
        document.getElementById('erro-itens').classList.remove('hidden');
    }
}
renderizarCarrinho();
</script>
@endsection