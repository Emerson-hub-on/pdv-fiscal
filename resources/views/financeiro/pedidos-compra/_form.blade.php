@php
    $ehEdicao = $pedido->exists;
    $inputCls = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-800 outline-none transition disabled:bg-gray-100 disabled:cursor-not-allowed';
    $labelCls = 'block text-sm font-medium text-gray-700 mb-1';
    $erroCls  = 'text-red-600 text-xs mt-1';

    // Itens: o que o operador digitou (se a validação falhou) ou o que está no banco
    if (is_array(old('itens'))) {
        $itensIniciais = collect(old('itens'))->map(fn ($i) => [
            'produto_id'          => $i['produto_id'] ?? null,
            'produto_variante_id' => $i['produto_variante_id'] ?? null,
            'codigo_barras'       => $i['codigo_barras'] ?? '',
            'descricao'           => $i['descricao'] ?? '',
            'unidade'             => $i['unidade'] ?? 'UN',
            'quantidade'          => $i['quantidade'] ?? 1,
        ])->values();
    } else {
        $itensIniciais = $pedido->itens->map(fn ($i) => [
            'produto_id'          => $i->produto_id,
            'produto_variante_id' => $i->produto_variante_id,
            'codigo_barras'       => $i->codigo_barras ?? '',
            'descricao'           => $i->descricao,
            'unidade'             => $i->unidade,
            'quantidade'          => (float) $i->quantidade,
        ])->values();
    }

    $fornecedorAtual = old('fornecedor_id')
        ? \App\Models\Fornecedor::find(old('fornecedor_id'))
        : $pedido->fornecedor;
@endphp

@if ($errors->any())
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            mostrarAviso({{ Illuminate\Support\Js::from($errors->all()) }}.join('\n'), 'erro');
        });
    </script>
@endif

<form id="form-pedido" method="POST"
      action="{{ $ehEdicao ? route('pedidos-compra.update', $pedido) : route('pedidos-compra.store') }}"
      class="flex flex-col gap-6 w-full max-w-5xl mt-2 mb-6">
    @csrf
    @if ($ehEdicao) @method('PUT') @endif

    {{-- ===================== Cabeçalho do pedido ===================== --}}
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('pedidos-compra.index') }}" title="Voltar para Pedidos de Compra"
               class="text-gray-500 hover:text-gray-800 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h1 class="text-lg font-semibold">
                {{ $ehEdicao ? 'Editar Pedido de Compra nº ' . $pedido->numeroExibicao() : 'Novo Pedido de Compra' }}
            </h1>
        </div>

        <div class="flex flex-col gap-4">
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="{{ $labelCls }}">Data do pedido <span class="text-red-500">*</span></label>
                    <input type="date" name="data_pedido"
                           value="{{ old('data_pedido', $pedido->data_pedido?->format('Y-m-d')) }}" class="{{ $inputCls }}">
                    @error('data_pedido') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $labelCls }}">Previsão de entrega <span class="text-gray-400 font-normal">(opcional)</span></label>
                    <input type="date" name="previsao_entrega"
                           value="{{ old('previsao_entrega', $pedido->previsao_entrega?->format('Y-m-d')) }}" class="{{ $inputCls }}">
                    @error('previsao_entrega') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $labelCls }}">Fornecedor <span class="text-red-500">*</span></label>
                    <input type="hidden" name="fornecedor_id" id="campo-fornecedor"
                           value="{{ old('fornecedor_id', $pedido->fornecedor_id) }}">
                    <div class="flex gap-2">
                        <button type="button" onclick="abrirModalFornecedor()"
                                class="w-full min-w-0 text-left border border-gray-300 rounded-lg px-3 py-2 text-sm hover:bg-gray-50">
                            <span id="texto-fornecedor-selecionado" class="block truncate">
                                @if ($fornecedorAtual)
                                    {{ $fornecedorAtual->nome_exibicao }} — {{ $fornecedorAtual->documento_formatado }}
                                @else
                                    Selecionar fornecedor...
                                @endif
                            </span>
                        </button>
                    </div>
                    @error('fornecedor_id') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="{{ $labelCls }}">Observação</label>
                <textarea name="observacao" rows="2" maxlength="1000"
                          placeholder="Ex.: condição de pagamento, forma de entrega, contato..."
                          class="{{ $inputCls }}">{{ old('observacao', $pedido->observacao) }}</textarea>
            </div>
        </div>
    </div>

    {{-- ===================== Dados da empresa (para o fornecedor emitir a NF) ===================== --}}
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-start justify-between gap-4 mb-1">
            <h2 class="text-sm font-semibold">Dados da empresa para emissão da nota fiscal</h2>
            <a href="{{ route('empresa.editar') }}" target="_blank"
               class="text-xs text-gray-500 hover:underline shrink-0">Editar dados da empresa</a>
        </div>
        <p class="text-xs text-gray-500 mb-4">Vêm do cadastro da empresa e vão no pedido para o fornecedor emitir a NF-e.</p>

        @unless ($empresa->exists)
            <div class="border border-red-300 bg-red-50 text-red-800 rounded-lg px-4 py-3 text-sm">
                Os dados da empresa ainda não foram cadastrados.
                <a href="{{ route('empresa.editar') }}" class="underline font-medium">Cadastrar agora</a>
            </div>
        @else
            @include('financeiro.pedidos-compra._dados-empresa', ['empresa' => $empresa])
        @endunless
    </div>

    {{-- ===================== Itens ===================== --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-sm font-semibold mb-1">Itens do pedido</h2>
        <p class="text-xs text-gray-500 mb-4">
            Leia ou digite o código de barras e tecle Enter. Se o código não estiver cadastrado, digite a descrição para incluir como item avulso.
        </p>

        {{-- Linha de inclusão --}}
        <div class="grid grid-cols-12 gap-2 items-end mb-4">
            <div class="col-span-3">
                <label class="{{ $labelCls }}">Código de barras</label>
                <input type="text" id="add-codigo" autocomplete="off" inputmode="numeric" class="{{ $inputCls }} font-mono">
            </div>
            <div class="col-span-5">
                <label class="{{ $labelCls }}">Descrição</label>
                <input type="text" id="add-descricao" maxlength="255" autocomplete="off" class="{{ $inputCls }}">
            </div>
            <div class="col-span-1">
                <label class="{{ $labelCls }}">Un.</label>
                <input type="text" id="add-unidade" maxlength="6" value="UN" class="{{ $inputCls }} uppercase">
            </div>
            <div class="col-span-2">
                <label class="{{ $labelCls }}">Quantidade</label>
                <input type="number" id="add-qtd" step="0.001" min="0.001" value="1" class="{{ $inputCls }}">
            </div>
            <div class="col-span-1">
                <button type="button" onclick="adicionarItem()" title="Adicionar item"
                        class="w-full bg-gray-700 text-amber-50 rounded-lg px-3 py-2 text-sm hover:bg-gray-500">+</button>
            </div>
        </div>

        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <table class="w-full text-sm table-fixed">
                <colgroup>
                    <col class="w-[18%]">
                    <col class="w-[44%]">
                    <col class="w-[10%]">
                    <col class="w-[20%]">
                    <col class="w-[8%]">
                </colgroup>
                <thead class="bg-gray-700 text-amber-50 text-xs uppercase">
                    <tr>
                        <th class="text-left px-2 py-2">Cód. barras</th>
                        <th class="text-left px-2 py-2">Descrição</th>
                        <th class="text-left px-2 py-2">Un.</th>
                        <th class="text-right px-2 py-2">Quantidade</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody id="linhas-itens" class="divide-y divide-gray-100"></tbody>
            </table>
        </div>
        <p id="resumo-itens" class="text-xs text-gray-500 mt-2"></p>
        @error('itens') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
    </div>

    <div class="flex gap-3">
        <button type="submit"
                class="bg-green-700 hover:bg-green-800 text-white rounded-lg px-6 py-2.5 text-sm font-medium">
            {{ $ehEdicao ? 'Salvar alterações' : 'Salvar pedido' }}
        </button>
        <a href="{{ route('pedidos-compra.index') }}"
           class="border border-gray-300 rounded-lg px-6 py-2.5 text-sm hover:bg-gray-50">Cancelar</a>
    </div>
</form>

<!-- Modal de busca de fornecedor -->
<div id="modal-busca-fornecedor" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 bg-linear-to-r from-slate-800 via-slate-900 to-slate-900">
            <h2 class="text-lg font-bold text-white">Selecionar Fornecedor</h2>
            <button type="button" onclick="fecharModalFornecedor()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>
        <div class="p-6">
            <input type="text" id="busca-fornecedor-modal" placeholder="Buscar por nome ou CPF/CNPJ..."
                   autocomplete="off"
                   class="w-full border border-gray-200 rounded-lg px-4 py-3 text-sm mb-4 focus:ring-2 focus:ring-slate-800 outline-none transition">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-400 uppercase tracking-wide border-b">
                        <th class="py-2">Nome</th>
                        <th class="py-2">CPF/CNPJ</th>
                        <th class="py-2">UF</th>
                        <th class="py-2">Telefone</th>
                    </tr>
                </thead>
                <tbody id="linhas-busca-fornecedor"></tbody>
            </table>
            <p class="text-xs text-gray-400 mt-3">Use ↑ ↓ para navegar e Enter para selecionar.</p>
        </div>
    </div>
</div>

<div id="area-avisos" class="fixed top-4 right-4 z-[100] flex flex-col gap-2 w-80 max-w-[90vw]"></div>

<script>
const itensIniciais      = {{ Illuminate\Support\Js::from($itensIniciais) }};
const urlBuscaProduto    = {{ Illuminate\Support\Js::from(route('pedidos-compra.buscar-produto')) }};
// reaproveita a busca de fornecedor que já existe em Contas a Pagar
const urlBuscaFornecedor = {{ Illuminate\Support\Js::from(route('contas-pagar.buscar-fornecedor')) }};

const esc = s => String(s ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
const clsCelula = 'w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-slate-800 outline-none transition';

let itens = itensIniciais.map(i => ({ ...i }));
let pendente = null; // produto encontrado na busca, aguardando a quantidade

// ---------------------------------------------------------------
// Avisos
// ---------------------------------------------------------------
function mostrarAviso(mensagem, tipo = 'erro') {
    const area = document.getElementById('area-avisos');
    const div = document.createElement('div');
    div.className = 'rounded-lg px-4 py-3 text-sm shadow-lg whitespace-pre-line border '
        + (tipo === 'erro' ? 'border-red-300 bg-red-50 text-red-800' : 'border-green-300 bg-green-50 text-green-800');
    div.textContent = mensagem;
    area.appendChild(div);
    setTimeout(() => div.remove(), 8000);
}

// Enter dentro de um input nunca envia o formulário (o leitor de código de barras manda Enter)
document.getElementById('form-pedido').addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT') e.preventDefault();
});

// ---------------------------------------------------------------
// Grade de itens
// ---------------------------------------------------------------
function renderizarItens() {
    const corpo = document.getElementById('linhas-itens');

    if (itens.length === 0) {
        corpo.innerHTML = '<tr><td colspan="5" class="px-3 py-6 text-center text-gray-400">Nenhum item incluído.</td></tr>';
    } else {
        corpo.innerHTML = itens.map((item, i) => {
            const avulso = !item.produto_id;
            return `
            <tr>
                <td class="px-2 py-2 font-mono text-xs text-gray-600 truncate" title="${esc(item.codigo_barras)}">
                    ${esc(item.codigo_barras) || '—'}
                    <input type="hidden" name="itens[${i}][codigo_barras]" value="${esc(item.codigo_barras)}">
                    <input type="hidden" name="itens[${i}][produto_id]" value="${esc(item.produto_id)}">
                    <input type="hidden" name="itens[${i}][produto_variante_id]" value="${esc(item.produto_variante_id)}">
                </td>
                <td class="px-2 py-2">
                    <input type="text" name="itens[${i}][descricao]" value="${esc(item.descricao)}" maxlength="255"
                           ${avulso ? '' : 'readonly'} oninput="itens[${i}].descricao = this.value"
                           class="${clsCelula} ${avulso ? '' : 'bg-gray-50 text-gray-700'}">
                </td>
                <td class="px-2 py-2">
                    <input type="text" name="itens[${i}][unidade]" value="${esc(item.unidade)}" maxlength="6"
                           oninput="itens[${i}].unidade = this.value.toUpperCase()" class="${clsCelula} uppercase">
                </td>
                <td class="px-2 py-2">
                    <input type="number" name="itens[${i}][quantidade]" value="${esc(item.quantidade)}" step="0.001" min="0.001"
                           oninput="itens[${i}].quantidade = this.value; atualizarResumo()" class="${clsCelula} text-right">
                </td>
                <td class="px-2 py-2 text-center">
                    <button type="button" onclick="removerItem(${i})" title="Remover item"
                            class="text-red-600 hover:bg-red-50 rounded px-2 py-1">&times;</button>
                </td>
            </tr>`;
        }).join('');
    }

    atualizarResumo();
}

function atualizarResumo() {
    const total = itens.reduce((s, i) => s + (parseFloat(i.quantidade) || 0), 0);
    document.getElementById('resumo-itens').textContent = itens.length === 0
        ? ''
        : `${itens.length} ${itens.length === 1 ? 'item' : 'itens'} · quantidade total: ${total.toLocaleString('pt-BR', { maximumFractionDigits: 3 })}`;
}

function removerItem(i) {
    itens.splice(i, 1);
    renderizarItens();
}

// ---------------------------------------------------------------
// Linha "Adicionar item"
// ---------------------------------------------------------------
const campoCodigo    = document.getElementById('add-codigo');
const campoDescricao = document.getElementById('add-descricao');
const campoUnidade   = document.getElementById('add-unidade');
const campoQtd       = document.getElementById('add-qtd');

function limparLinhaAdicionar() {
    pendente = null;
    campoCodigo.value = '';
    campoDescricao.value = '';
    campoDescricao.readOnly = false;
    campoDescricao.classList.remove('bg-gray-50');
    campoUnidade.value = 'UN';
    campoQtd.value = 1;
    campoCodigo.focus();
}

async function buscarProdutoPorCodigo() {
    const codigo = campoCodigo.value.trim();
    if (!codigo) { campoDescricao.focus(); return; }

    try {
        const resp = await fetch(`${urlBuscaProduto}?codigo=${encodeURIComponent(codigo)}`, {
            headers: { 'Accept': 'application/json' },
        });
        const json = await resp.json().catch(() => ({}));

        if (resp.status === 404) {
            pendente = null;
            mostrarAviso((json.message ?? 'Código não encontrado.') + '\nDigite a descrição para incluir como item avulso.');
            campoDescricao.focus();
            return;
        }
        if (!resp.ok) {
            pendente = null;
            mostrarAviso(json.message ?? `Erro ${resp.status} ao consultar o produto.`);
            campoCodigo.select();
            return;
        }

        pendente = json;
        campoDescricao.value = json.descricao;
        campoDescricao.readOnly = true;
        campoDescricao.classList.add('bg-gray-50');
        campoUnidade.value = json.unidade || 'UN';
        campoQtd.focus();
        campoQtd.select();
    } catch (e) {
        mostrarAviso('Falha ao buscar o produto:\n' + e.message);
    }
}

function adicionarItem() {
    const descricao  = campoDescricao.value.trim();
    const quantidade = parseFloat(campoQtd.value);

    if (!descricao) return mostrarAviso('Informe a descrição do item (ou leia um código cadastrado).');
    if (!(quantidade > 0)) return mostrarAviso('A quantidade deve ser maior que zero.');

    // mesmo produto/variação já na lista: soma a quantidade em vez de duplicar a linha
    const existente = pendente
        ? itens.find(i => i.produto_id == pendente.produto_id && (i.produto_variante_id ?? null) == (pendente.produto_variante_id ?? null))
        : null;

    if (existente) {
        existente.quantidade = Math.round(((parseFloat(existente.quantidade) || 0) + quantidade) * 1000) / 1000;
    } else {
        itens.push({
            produto_id:          pendente?.produto_id ?? null,
            produto_variante_id: pendente?.produto_variante_id ?? null,
            codigo_barras:       pendente?.codigo_barras ?? campoCodigo.value.trim(),
            descricao,
            unidade:             (campoUnidade.value.trim() || 'UN').toUpperCase(),
            quantidade,
        });
    }

    renderizarItens();
    limparLinhaAdicionar();
}

campoCodigo.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); buscarProdutoPorCodigo(); }
});
campoCodigo.addEventListener('input', () => { // código alterado depois da busca: descarta o produto encontrado
    if (pendente) {
        pendente = null;
        campoDescricao.value = '';
        campoDescricao.readOnly = false;
        campoDescricao.classList.remove('bg-gray-50');
    }
});
campoDescricao.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); campoQtd.focus(); campoQtd.select(); }
});
campoUnidade.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); campoQtd.focus(); campoQtd.select(); }
});
campoQtd.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); adicionarItem(); }
});

// ---------------------------------------------------------------
// Busca de fornecedor (modal)
// ---------------------------------------------------------------
const inputBuscaFornecedor  = document.getElementById('busca-fornecedor-modal');
const linhasBuscaFornecedor = document.getElementById('linhas-busca-fornecedor');
let fornecedoresAtuais = [];
let indiceFornecedor = -1;
let timeoutBuscaFornecedor;
let contadorBuscaFornecedor = 0; // descarta respostas antigas se o operador digitar rápido

function abrirModalFornecedor() {
    const modal = document.getElementById('modal-busca-fornecedor');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    inputBuscaFornecedor.value = '';
    inputBuscaFornecedor.focus();
    buscarFornecedores('');
}

function fecharModalFornecedor() {
    const modal = document.getElementById('modal-busca-fornecedor');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    fornecedoresAtuais = [];
    indiceFornecedor = -1;
}

async function buscarFornecedores(termo) {
    const minhaBusca = ++contadorBuscaFornecedor;
    let dados;

    try {
        const resp = await fetch(`${urlBuscaFornecedor}?termo=${encodeURIComponent(termo)}`, {
            headers: { 'Accept': 'application/json' },
        });
        if (!resp.ok) throw new Error(`Erro ${resp.status} ao consultar o servidor.`);
        dados = await resp.json();
    } catch (e) {
        if (minhaBusca === contadorBuscaFornecedor) {
            mostrarAviso('Falha ao buscar fornecedores:\n' + e.message, 'erro');
            fornecedoresAtuais = [];
            indiceFornecedor = -1;
            renderizarFornecedores();
        }
        return;
    }

    if (minhaBusca !== contadorBuscaFornecedor) return;

    fornecedoresAtuais = dados;
    indiceFornecedor = dados.length > 0 ? 0 : -1;
    renderizarFornecedores();
}

function renderizarFornecedores() {
    if (fornecedoresAtuais.length === 0) {
        linhasBuscaFornecedor.innerHTML = '<tr><td colspan="4" class="p-3 text-sm text-gray-400 text-center">Nenhum fornecedor encontrado.</td></tr>';
        return;
    }

    linhasBuscaFornecedor.innerHTML = fornecedoresAtuais.map((f, index) => {
        const destacado = index === indiceFornecedor;
        return `
            <tr class="cursor-pointer border-b border-gray-100 transition ${destacado ? 'bg-slate-800 text-white' : 'hover:bg-gray-50'}"
                onclick="selecionarFornecedor(${index})">
                <td class="py-3 font-medium">${esc(f.nome)}</td>
                <td class="py-3 font-mono text-sm ${destacado ? 'text-slate-300' : 'text-gray-500'}">${esc(f.cpf_cnpj)}</td>
                <td class="py-3">${esc(f.uf)}</td>
                <td class="py-3">${esc(f.telefone)}</td>
            </tr>
        `;
    }).join('');

    linhasBuscaFornecedor.children[indiceFornecedor]?.scrollIntoView({ block: 'nearest' });
}

function selecionarFornecedor(index) {
    const f = fornecedoresAtuais[index];
    if (!f) return;

    document.getElementById('campo-fornecedor').value = f.id;
    document.getElementById('texto-fornecedor-selecionado').textContent = `${f.nome} — ${f.cpf_cnpj}`;
    fecharModalFornecedor();
}

inputBuscaFornecedor?.addEventListener('input', () => {
    clearTimeout(timeoutBuscaFornecedor);
    timeoutBuscaFornecedor = setTimeout(() => buscarFornecedores(inputBuscaFornecedor.value.trim()), 300);
});

inputBuscaFornecedor?.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { fecharModalFornecedor(); return; }
    if (fornecedoresAtuais.length === 0) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        indiceFornecedor = (indiceFornecedor + 1) % fornecedoresAtuais.length;
        renderizarFornecedores();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        indiceFornecedor = (indiceFornecedor - 1 + fornecedoresAtuais.length) % fornecedoresAtuais.length;
        renderizarFornecedores();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (indiceFornecedor >= 0) selecionarFornecedor(indiceFornecedor);
    }
});

renderizarItens();
campoCodigo.focus();
</script>
